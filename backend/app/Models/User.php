<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;


    /**
     * Roles attached to this user.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    /**
     * All permissions granted to this user through roles.
     */
    public function permissions()
    {
        return Permission::whereHas('roles', function ($query) {
            $query->whereIn('roles.id', $this->roles->pluck('id'));
        })->get();
    }

    /**
     * Check if user has a role by slug.
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles->contains('slug', $roleSlug);
    }

    /**
     * Check if user has a permission by slug.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        // Director is god mode, shortcut
        if ($this->isDirector()) {
            return true;
        }

        return $this->permissions()
            ->contains('slug', $permissionSlug);
    }

    /**
     * Convenience: is this user a Director.
     */
    public function isDirector(): bool
    {
        return $this->hasRole('director');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'rank',
        'rank_level',
        // Discord fields
        'discord_id',
        'discord_name',
        'discord_avatar',
        // RSI fields
        'rsi_handle',
        'rsi_verified_at',

        // Verification fields
        'verification_code',
        'verification_expires_at',
        'global_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rank_level' => 'integer',
            'rsi_verified_at' => 'datetime',
        ];
    }
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
    
    public function squadron()
    {
        // Convenience: the "main" squadron for a user, if you treat them as having 0–1
        return $this->hasOne(SquadronMember::class)
            ->where('membership_status', SquadronMember::STATUS_ACTIVE);
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_BANNED = 'banned';

    /**
     * Check if the user has ANY of the given role slugs.
     *
     * Example: $user->hasAnyRole(['lieutenant', 'commander_squadron'])
     */
    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles->pluck('slug')->intersect($slugs)->isNotEmpty();
    }

    /**
     * Check if the user has ANY of the given permissions.
     *
     * Example: $user->hasAnyPermission(['event.host.small', 'event.host.medium'])
     */
    public function hasAnyPermission(array $slugs): bool
    {
        if ($this->isDirector()) {
            return true;
        }

        return $this->permissions()
        ->pluck('slug')
        ->intersect($slugs)
        ->isNotEmpty();
    }


}