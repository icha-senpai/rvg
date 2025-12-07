<?php

namespace App\Domain\AccessControl;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AccessService
{
    /**
     * Build a UserContext wrapper.
     */
    public function context(User $user): UserContext
    {
        return UserContext::for($user);
    }

    /**
     * Basic permission check.
     *
     * Example:
     *   $access->can($user, 'operation.host.small');
     */
    public function can(User $user, string $permission, ?Model $context = null): bool
    {
        $ctx = $this->context($user);

        // Future place for contextual checks (per squadron, per company, etc)
        return $ctx->hasPermission($permission);
    }

    /**
     * Check if user has any of given permissions.
     */
    public function any(User $user, array $permissions, ?Model $context = null): bool
    {
        foreach ($permissions as $perm) {
            if ($this->can($user, $perm, $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check role by slug (uses UserContext).
     */
    public function hasRole(User $user, string $role): bool
    {
        return $this->context($user)->hasRole($role);
    }

    public function hasAnyRole(User $user, array $roles): bool
    {
        return $this->context($user)->hasAnyRole($roles);
    }

    /**
     * Director / Tech Director global override.
     */
    public function isDirectorLike(User $user): bool
    {
        return $this->context($user)->isDirectorLike();
    }
}
