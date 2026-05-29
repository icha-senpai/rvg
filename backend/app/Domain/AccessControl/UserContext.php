<?php

namespace App\Domain\AccessControl;

use App\Models\Squadron;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class UserContext
{
    protected User $user;
    protected SquadronMembershipReadService $memberships;

    protected ?Collection $roles = null;
    protected ?Collection $permissions = null;

    public function __construct(User $user, ?SquadronMembershipReadService $memberships = null)
    {
        $this->user = $user;
        $this->memberships = $memberships ?? app(SquadronMembershipReadService::class);
    }

    public static function for(User $user): self
    {
        return new self($user);
    }

    /**
     * Core: get all roles for this user.
     */
    public function roles(): Collection
    {
        if ($this->roles !== null) {
            return $this->roles;
        }

        $this->roles = $this->user->roles()->get();

        return $this->roles;
    }

    /**
     * Core: get all permissions for this user, cached.
     */
    public function permissions(): Collection
    {
        if ($this->permissions !== null) {
            return $this->permissions;
        }

        $cacheKey = 'user_permissions_' . $this->user->id;

        $this->permissions = Cache::remember($cacheKey, now()->addMinutes(5), function () {
            return $this->user->permissions();
        });

        return $this->permissions;
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->contains('slug', $slug);
    }

    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles()
            ->whereIn('slug', $slugs)
            ->isNotEmpty();
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->contains('slug', $slug);
    }

    public function hasAnyPermission(array $slugs): bool
    {
        foreach ($slugs as $slug) {
            if ($this->hasPermission($slug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Director / Tech Director global override.
     */
    public function isDirectorLike(): bool
    {
        return $this->hasAnyRole(['director', 'tech_director']);
    }

    /**
     * Squadron helpers, mapped to your existing methods.
     */

    public function isSquadronLeader(Squadron $squadron): bool
    {
        return $this->memberships->isLeader($this->user, $squadron);
    }

    public function isSquadronLieutenant(Squadron $squadron): bool
    {
        return $this->memberships->isLieutenant($this->user, $squadron);
    }

    /**
     * Useful when you want both roles and rank-level.
     */
    public function rankLevel(): ?int
    {
        return $this->user->rank_level ?? null;
    }

    public function user(): User
    {
        return $this->user;
    }

    /**
     * Hard reset cached permissions (after role/perm changes).
     */
    public function flushPermissionsCache(): void
    {
        Cache::forget('user_permissions_' . $this->user->id);
        $this->permissions = null;
    }
}
