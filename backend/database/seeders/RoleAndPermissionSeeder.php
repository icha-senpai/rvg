<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Create core roles
            $roles = [
                [
                    'name'        => 'Director',
                    'slug'        => 'director',
                    'description' => 'Full system access. Reserved for top leadership.',
                    'is_system'   => true,
                ],
                [
                    'name'        => 'Member',
                    'slug'        => 'member',
                    'description' => 'Default member permissions.',
                    'is_system'   => true,
                ],
                // You and Pheried can expand this later with functional roles like:
                // ['name' => 'Squadron Lead', 'slug' => 'squadron.lead', ...],
                // ['name' => 'Event Lead', 'slug' => 'event.lead', ...],
            ];

            $roleModels = [];
            foreach ($roles as $data) {
                $roleModels[$data['slug']] = Role::updateOrCreate(
                    ['slug' => $data['slug']],
                    $data
                );
            }

            // 2. Create core permissions
            $permissions = [
                // Squadron management
                [
                    'name'        => 'View squadrons',
                    'slug'        => 'squadron.view',
                    'description' => 'View squadron list and details.',
                ],
                [
                    'name'        => 'Manage squadrons',
                    'slug'        => 'squadron.manage',
                    'description' => 'Create, update, and delete squadrons.',
                ],
                [
                    'name'        => 'Manage squadron members',
                    'slug'        => 'squadron.members.manage',
                    'description' => 'Add or remove members from squadrons.',
                ],

                // Events
                [
                    'name'        => 'Create events',
                    'slug'        => 'event.create',
                    'description' => 'Create new events.',
                ],
                [
                    'name'        => 'Manage all events',
                    'slug'        => 'event.manage',
                    'description' => 'Edit and delete events.',
                ],

                // Missions
                [
                    'name'        => 'Create missions',
                    'slug'        => 'mission.create',
                    'description' => 'Create new missions.',
                ],
                [
                    'name'        => 'Manage all missions',
                    'slug'        => 'mission.manage',
                    'description' => 'Edit and delete missions.',
                ],

                // User and system
                [
                    'name'        => 'View users',
                    'slug'        => 'user.view',
                    'description' => 'View user list and profiles.',
                ],
                [
                    'name'        => 'Manage users',
                    'slug'        => 'user.manage',
                    'description' => 'Ban, suspend, or update users.',
                ],
                [
                    'name'        => 'View audit log',
                    'slug'        => 'system.audit',
                    'description' => 'View system-wide logs.',
                ],
                [
                    'name'        => 'Manage system settings',
                    'slug'        => 'system.settings',
                    'description' => 'Update system configuration.',
                ],
            ];

            $permissionModels = [];
            foreach ($permissions as $data) {
                $permissionModels[$data['slug']] = Permission::updateOrCreate(
                    ['slug' => $data['slug']],
                    $data
                );
            }

            // 3. Attach permissions to roles

            // Director: gets everything
            $director = $roleModels['director'];
            $director->permissions()->sync(
                collect($permissionModels)->pluck('id')->all()
            );

            // Member: basic view-only stuff
            $member = $roleModels['member'];
            $member->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['event.create']->id,   // maybe let members propose events
                $permissionModels['mission.create']->id, // and missions
            ]);
        });
    }
}
