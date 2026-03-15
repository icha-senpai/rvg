<?php

namespace App\Domain\AccessControl\Traits;

use App\Models\Role;
use App\Models\Permission;

/**
 * Adds role, permission, and squadron-rank helper methods to the owning model.
 */
trait HasRolesAndPermissions
{
    /**
     * Return the roles directly assigned to the model.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * Resolve the unique permission set granted through the model's roles.
     */
    public function permissions()
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->unique('id');
    }

    /**
     * Check whether the model has the given role slug.
     */
    public function hasRole(string $slug): bool
    {
        return $this->roles->pluck('slug')->contains($slug);
    }

    /**
     * Check whether the model has any role from the provided list.
     */
    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles->pluck('slug')->intersect($slugs)->isNotEmpty();
    }

    /**
     * Check whether any assigned role grants the given permission slug.
     */
    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->pluck('slug')->contains($slug);
    }

    /**
     * Check whether any assigned role grants one of the given permissions.
     */
    public function hasAnyPermission(array $slugs): bool
    {
        return $this->permissions()->pluck('slug')->intersect($slugs)->isNotEmpty();
    }

    /**
     * Check whether the model is the current leader of the given squadron.
     */
    public function isSquadronLeader($squadron): bool
    {
        return $this->id === $squadron->leader_id;
    }

    /**
     * Check whether the model is an active lieutenant of the given squadron.
     */
    public function isSquadronLieutenant($squadron): bool
    {
        return $squadron->members()
            ->where('user_id', $this->id)
            ->where('membership_status', 'active')
            ->where('role', 'lieutenant')
            ->exists();
    }
}
