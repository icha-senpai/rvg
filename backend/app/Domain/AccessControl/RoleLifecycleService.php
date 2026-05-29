<?php

namespace App\Domain\AccessControl;

use App\Models\Role;

class RoleLifecycleService
{
    public function create(array $data): Role
    {
        return Role::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);
    }

    public function update(Role $role, array $data): Role
    {
        $role->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);

        return $role->fresh();
    }

    public function delete(Role $role): void
    {
        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();
    }
}
