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
    protected ?array $roleSlugLookup = null;
    protected ?array $permissionSlugLookup = null;

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

        if ($this->user->relationLoaded('roles')) {
            $this->roles = $this->user->getRelation('roles');

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

        if ($this->rolesHaveLoadedPermissions()) {
            $this->permissions = $this->roles()
                ->pluck('permissions')
                ->flatten()
                ->unique('id')
                ->values();

            return $this->permissions;
        }

        $cacheKey = 'user_permissions_' . $this->user->id;

        $this->permissions = Cache::remember($cacheKey, now()->addMinutes(5), function () {
            return $this->user->permissions();
        });

        return $this->permissions;
    }

    protected function rolesHaveLoadedPermissions(): bool
    {
        return $this->user->relationLoaded('roles')
            && $this->roles()->every(fn ($role) => $role->relationLoaded('permissions'));
    }

    public function hasRole(string $slug): bool
    {
        return isset($this->roleSlugLookup()[$slug]);
    }

    public function hasAnyRole(array $slugs): bool
    {
        $roleSlugLookup = $this->roleSlugLookup();

        foreach ($slugs as $slug) {
            if (isset($roleSlugLookup[$slug])) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $slug): bool
    {
        return isset($this->permissionSlugLookup()[$slug]);
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
        $this->permissionSlugLookup = null;
    }

    protected function roleSlugLookup(): array
    {
        if ($this->roleSlugLookup !== null) {
            return $this->roleSlugLookup;
        }

        $this->roleSlugLookup = $this->roles()
            ->pluck('slug')
            ->filter()
            ->mapWithKeys(fn ($slug) => [$slug => true])
            ->all();

        return $this->roleSlugLookup;
    }

    protected function permissionSlugLookup(): array
    {
        if ($this->permissionSlugLookup !== null) {
            return $this->permissionSlugLookup;
        }

        $this->permissionSlugLookup = $this->permissions()
            ->pluck('slug')
            ->filter()
            ->mapWithKeys(fn ($slug) => [$slug => true])
            ->all();

        return $this->permissionSlugLookup;
    }
}
