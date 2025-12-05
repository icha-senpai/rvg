<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use App\Models\Role;
use App\Models\Permission;
use App\Models\Squadron;
use App\Models\SquadronMember;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /* --------------------------------------
     |  ROLES / PERMISSIONS
     -------------------------------------- */

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')
            ->withTimestamps();
    }

    public function permissions()
    {
        return Permission::whereHas('roles', function ($query) {
            $query->whereIn('roles.id', $this->roles->pluck('id'));
        })->get();
    }

    public function hasRole(string $roleSlug): bool
    {
        return $this->roles->contains('slug', $roleSlug);
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->isDirector()) {
            return true;
        }

        return $this->permissions()->contains('slug', $permissionSlug);
    }

    public function isDirector(): bool
    {
        return $this->hasRole('director');
    }

    /* --------------------------------------
     | MASS ASSIGN / CASTS
     -------------------------------------- */

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
            'rsi_verified_at'       => 'datetime',
            'preferred_roles'       => 'array',
            'notification_settings' => 'array',
            'personal_tags'         => 'array',
        ];
    }

    /* --------------------------------------
     | RANK HELPERS
     -------------------------------------- */

    public function setRank(string $rank): void
    {
        $map = [
            'member'         => 1,
            'lieutenant'     => 2,
            'commander'      => 3,
            'wing_commander' => 4,
            'admiral'        => 5,
            'grand_admiral'  => 6,
        ];

        $this->attributes['rank'] = $rank;
        $this->attributes['rank_level'] = $map[$rank] ?? 1;
        $this->save();
    }

    public function getRankNameAttribute()
    {
        return [
            1 => 'Member',
            2 => 'Lieutenant',
            3 => 'Commander',
            4 => 'Wing Commander',
            5 => 'Admiral',
            6 => 'Grand Admiral',
        ][$this->rank_level] ?? 'Unknown';
    }

    /* --------------------------------------
     | SQUADRON MEMBERSHIP LOGIC
     -------------------------------------- */

    public function squadronMemberships()
    {
        return $this->hasMany(SquadronMember::class);
    }

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

    public function squadronMembershipFor(Squadron $squadron): ?SquadronMember
    {
        return $this->squadronMemberships()
            ->where('squadron_id', $squadron->id)
            ->where('membership_status', SquadronMember::STATUS_ACTIVE)
            ->latest('joined_at')
            ->first();
    }

    public function isSquadronLeader(Squadron $squadron): bool
    {
        $membership = $this->squadronMembershipFor($squadron);

        return $membership?->isLeader() ?? false;
    }

    public function isSquadronLieutenant(Squadron $squadron): bool
    {
        $membership = $this->squadronMembershipFor($squadron);

        return $membership
            && $membership->role === SquadronMember::ROLE_LIEUTENANT
            && $membership->membership_status === SquadronMember::STATUS_ACTIVE;
    }

    /* --------------------------------------
     | USER PREFERENCES
     -------------------------------------- */

    public function preferences()
    {
        return $this->hasOne(MemberPreference::class);
    }

    /* --------------------------------------
     | STATUS CONSTANTS
     -------------------------------------- */

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE  = 'active';
    public const STATUS_BANNED  = 'banned';
}
