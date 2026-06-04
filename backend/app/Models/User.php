<?php

namespace App\Models;

use App\Domain\AccessControl\RoleHierarchy;
use App\Domain\AccessControl\SquadronMembershipReadService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\PromotionOffer;
use App\Domain\AccessControl\Traits\HasRolesAndPermissions;
use Illuminate\Support\Facades\Cache;

/**
 * Represents an authenticated application user together with their access,
 * verification, squadron, preference, and media relationships.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRolesAndPermissions;

    protected $fillable = [
        'name',
        'email',
        'password',
        'rank',
        'rank_level',
        'discord_id',
        'discord_name',
        'discord_avatar',
        'verification_code',
        'verification_expires_at',
        'global_status',
        'bio',
        'timezone',
        'region',
        'favorite_ships',
        'favorite_guns',
        'primary_role',
        'secondary_role',
        'experience_ratings',
        'preferred_gameplay_style',
        'callsign',
        'typical_op_commitment',
        'preferred_roles',
        'notification_settings',
        'personal_tags',
        'availability_status',
        'loa_note',
        'rsi_handle',
        'rsi_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
    ];

    protected $appends = [
        'rank_name',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'     => 'datetime',
            'password'              => 'hashed',
            'rank_level'            => 'integer',
            'operations_joined_count' => 'integer',
            'operations_left_early_count' => 'integer',
            'operations_completed_count' => 'integer',
            'operations_no_show_count' => 'integer',
            'operations_created_count' => 'integer',
            'operations_canceled_count' => 'integer',
            'operations_success_count' => 'integer',
            'operations_failed_count' => 'integer',
            'verification_expires_at' => 'datetime',
            'rsi_verified_at'       => 'datetime',
            'favorite_ships'         => 'array',
            'favorite_guns'          => 'array',
            'experience_ratings'     => 'array',
            'preferred_roles'       => 'array',
            'notification_settings' => 'array',
            'personal_tags'         => 'array',
        ];
    }

    /**
     * Set the stored rank slug and keep the legacy numeric rank level in sync.
     */
    public function setRank(string $rank): void
    {
        $this->attributes['rank'] = $rank;
        $this->attributes['rank_level'] = RoleHierarchy::levelFor($rank) ?: 1;
        $this->save();
    }

    /**
     * Return the human-readable rank label for the stored rank level.
     */
    public function getRankNameAttribute()
    {
        return [
            1 => 'Member',
            2 => 'Lieutenant',
            3 => 'C.I.T (Commander in Training)',
            4 => 'Commander',
            5 => 'Wing Commander',
            6 => 'Admiral',
            7 => 'Grand Admiral',
            8 => 'Director',
        ][$this->rank_level] ?? 'Unknown';
    }

    /**
     * Return all squadron membership rows for the user.
     */
    public function squadronMemberships()
    {
        return $this->hasMany(SquadronMember::class);
    }

    /**
     * Return the squadrons related to the user through membership rows.
     */
    public function squadrons()
    {
        return $this->belongsToMany(Squadron::class, 'squadron_members')
            ->withPivot([
                'membership_status',
                'role',
                'joined_at',
                'left_at',
                'removed_at',
            ])
            ->withTimestamps();
    }

    /**
     * Return the user's current active membership for the given squadron.
     *
     * @deprecated Prefer App\Domain\AccessControl\SquadronMembershipReadService
     * when resolving squadron membership outside the model layer.
     */
    public function squadronMembershipFor(Squadron $squadron): ?SquadronMember
    {
        return app(SquadronMembershipReadService::class)
            ->activeMembership($this, $squadron);
    }

    /**
     * Check whether the user is the active leader of the given squadron.
     *
     * @deprecated Prefer App\Domain\AccessControl\SquadronMembershipReadService
     * or AccessService when resolving squadron leadership checks.
     */
    public function isSquadronLeader(Squadron $squadron): bool
    {
        return app(SquadronMembershipReadService::class)
            ->isLeader($this, $squadron);
    }

    /**
     * Check whether the user is an active lieutenant of the given squadron.
     *
     * @deprecated Prefer App\Domain\AccessControl\SquadronMembershipReadService
     * or AccessService when resolving squadron lieutenant checks.
     */
    public function isSquadronLieutenant(Squadron $squadron): bool
    {
        return app(SquadronMembershipReadService::class)
            ->isLieutenant($this, $squadron);
    }

    /**
     * Return the user's one-to-one preference record.
     */
    public function preferences()
    {
        return $this->hasOne(MemberPreference::class);
    }

    public function promotionOffersReceived()
    {
        return $this->hasMany(PromotionOffer::class, 'member_id');
    }

    public function promotionOffersCreated()
    {
        return $this->hasMany(PromotionOffer::class, 'promoter_id');
    }

    /**
     * Return all polymorphic media attached to the user.
     */
    public function media()
    {
        return $this->morphMany(\App\Models\Media::class, 'mediable');
    }

    public function ledgerAccounts()
    {
        return $this->hasMany(LedgerAccount::class);
    }

    public function ledgerTransactions()
    {
        return $this->hasMany(LedgerTransaction::class);
    }

    public function ledgerTrades()
    {
        return $this->hasMany(LedgerTrade::class);
    }

    public function ledgerInventoryItems()
    {
        return $this->hasMany(LedgerInventoryItem::class);
    }

    public function ledgerShipAssets()
    {
        return $this->hasMany(LedgerShipAsset::class);
    }

    /**
     * Return the user's most recent avatar media record.
     */
    public function avatar()
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')
            ->where('collection', \App\Models\Media::COLLECTION_AVATAR)
            ->latest();
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE  = 'active';
    public const STATUS_BANNED  = 'banned';

    /**
     * Clear cached role and permission payloads whenever the user record is
     * saved so downstream access checks see fresh state.
     */
    protected static function booted()
    {
        static::saved(function (User $user) {
            Cache::forget("user_roles_{$user->id}");
            Cache::forget("user_permissions_{$user->id}");
        });
    }
}
