<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use App\Models\Squadron;
use App\Models\SquadronMember;
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

    protected function casts(): array
    {
        return [
            'email_verified_at'     => 'datetime',
            'password'              => 'hashed',
            'rank_level'            => 'integer',
            'operations_joined_count' => 'integer',
            'operations_left_early_count' => 'integer',
            'operations_completed_count' => 'integer',
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
        $map = [
            'member'         => 1,
            'lieutenant'     => 2,
            'cit'            => 3,
            'commander'      => 4,
            'wing_commander' => 5,
            'admiral'        => 6,
            'grand_admiral'  => 7,
        ];

        $this->attributes['rank'] = $rank;
        $this->attributes['rank_level'] = $map[$rank] ?? 1;
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
     */
    public function squadronMembershipFor(Squadron $squadron): ?SquadronMember
    {
        return $this->squadronMemberships()
            ->where('squadron_id', $squadron->id)
            ->where('membership_status', SquadronMember::STATUS_ACTIVE)
            ->latest('joined_at')
            ->first();
    }

    /**
     * Check whether the user is the active leader of the given squadron.
     */
    public function isSquadronLeader(Squadron $squadron): bool
    {
        $membership = $this->squadronMembershipFor($squadron);

        return $membership?->isLeader() ?? false;
    }

    /**
     * Check whether the user is an active lieutenant of the given squadron.
     */
    public function isSquadronLieutenant(Squadron $squadron): bool
    {
        $membership = $this->squadronMembershipFor($squadron);

        return $membership
            && $membership->role === SquadronMember::ROLE_LIEUTENANT
            && $membership->membership_status === SquadronMember::STATUS_ACTIVE;
    }

    /**
     * Return the user's one-to-one preference record.
     */
    public function preferences()
    {
        return $this->hasOne(MemberPreference::class);
    }

    /**
     * Return all polymorphic media attached to the user.
     */
    public function media()
    {
        return $this->morphMany(\App\Models\Media::class, 'mediable');
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
