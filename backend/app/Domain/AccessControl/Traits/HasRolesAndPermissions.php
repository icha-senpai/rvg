<?php

namespace App\Domain\AccessControl\Traits;

use App\Models\Role;
use App\Models\Permission;

trait HasRolesAndPermissions
{
    /* -----------------------------------------
     * RELATIONSHIPS
     * -------------------------------------- */

    public function roles()
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function permissions()
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->unique('id');
    }

    /* -----------------------------------------
     * ROLE CHECKS
     * -------------------------------------- */

    public function hasRole(string $slug): bool
    {
        return $this->roles->pluck('slug')->contains($slug);
    }

    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles->pluck('slug')->intersect($slugs)->isNotEmpty();
    }

    /* -----------------------------------------
     * PERMISSION CHECKS
     * -------------------------------------- */

    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->pluck('slug')->contains($slug);
    }

    public function hasAnyPermission(array $slugs): bool
    {
        return $this->permissions()->pluck('slug')->intersect($slugs)->isNotEmpty();
    }

    /* -----------------------------------------
     * SQUADRON HELPERS (YOUR EXISTING LOGIC)
     * -------------------------------------- */

    public function isSquadronLeader($squadron): bool
    {
        return $this->id === $squadron->leader_id;
    }

    public function isSquadronLieutenant($squadron): bool
    {
        return $squadron->members()
            ->where('user_id', $this->id)
            ->where('role', 'lieutenant')
            ->exists();
    }
}
