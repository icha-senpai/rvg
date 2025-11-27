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

            /**
             * -----------------------------------------
             * 1. ROLES
             * -----------------------------------------
             */
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
                [
                    'name'        => 'Lieutenant',
                    'slug'        => 'lieutenant',
                    'description' => 'Junior officer. Can host smaller events.',
                    'is_system'   => false,
                ],
                [
                    'name'        => 'Commander – Squadron',
                    'slug'        => 'commander_squadron',
                    'description' => 'Micro commander: manages their own squadron and LTs.',
                    'is_system'   => false,
                ],
                [
                    'name'        => 'Commander – Staff',
                    'slug'        => 'commander_staff',
                    'description' => 'Macro commander: manages a domain (Fleet, Logistics, etc).',
                    'is_system'   => false,
                ],
                [
                    'name'        => 'Wing Commander',
                    'slug'        => 'wing_commander',
                    'description' => 'Oversees multiple squadrons and their commanders.',
                    'is_system'   => false,
                ],
                [
                    'name'        => 'Admiral',
                    'slug'        => 'admiral',
                    'description' => 'High-level division command.',
                    'is_system'   => false,
                ],
                [
                    'name'        => 'Grand Admiral',
                    'slug'        => 'grand_admiral',
                    'description' => 'Org-wide strategic command.',
                    'is_system'   => false,
                ],
                [
                    'name'        => 'Viewer',
                    'slug'        => 'viewer',
                    'description' => 'Read-only global visibility.',
                    'is_system'   => false,
                ],
                [
                    'name'        => 'Mission Commander',
                    'slug'        => 'mission_commander',
                    'description' => 'Analytics and doctrine: evaluates missions and ops.',
                    'is_system'   => false,
                ],
                [
                    'name'        => 'Technical Director',
                    'slug'        => 'tech_director',
                    'description' => 'Manages technical infrastructure & RBAC.',
                    'is_system'   => true,
                ],
                [
                    'name'        => 'Technical Team',
                    'slug'        => 'tech_team',
                    'description' => 'Assists with technical operations.',
                    'is_system'   => false,
                ],
            ];

            $roleModels = [];
            foreach ($roles as $data) {
                $roleModels[$data['slug']] = Role::updateOrCreate(
                    ['slug' => $data['slug']],
                    $data
                );
            }

            /**
             * -----------------------------------------
             * 2. PERMISSIONS
             * -----------------------------------------
             */
            $permissions = [

                // Squadron
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

                // Event host tiers
                [
                    'name'        => 'Host small events',
                    'slug'        => 'event.host.small',
                    'description' => 'Host small squad-level events.',
                ],
                [
                    'name'        => 'Host medium events',
                    'slug'        => 'event.host.medium',
                    'description' => 'Host medium multi-squad or domain events.',
                ],
                [
                    'name'        => 'Host large events',
                    'slug'        => 'event.host.large',
                    'description' => 'Host division-wide or large-scale events.',
                ],
                [
                    'name'        => 'Host org-wide events',
                    'slug'        => 'event.host.org',
                    'description' => 'Host org-wide strategic operations.',
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

                // NEW — Mission view
                [
                    'name'        => 'View missions',
                    'slug'        => 'mission.view',
                    'description' => 'View mission listings and details.',
                ],

                // NEW — Mission member management
                [
                    'name'        => 'Manage mission members',
                    'slug'        => 'mission.members.manage',
                    'description' => 'Add/remove mission members and adjust attendance.',
                ],

                // Users / System
                [
                    'name'        => 'View users',
                    'slug'        => 'user.view',
                    'description' => 'View user list and profiles.',
                ],
                [
                    'name'        => 'Manage users',
                    'slug'        => 'user.manage',
                    'description' => 'Manage user data, roles, and status.',
                ],
                [
                    'name'        => 'Manage roles',
                    'slug'        => 'system.manage_roles',
                    'description' => 'Create and assign roles.',
                ],
                [
                    'name'        => 'Manage permissions',
                    'slug'        => 'system.manage_permissions',
                    'description' => 'Assign permissions to roles.',
                ],
                [
                    'name'        => 'Manage system settings',
                    'slug'        => 'system.settings',
                    'description' => 'Modify core system settings.',
                ],

                // Domain / Division
                [
                    'name'        => 'Manage domain resources',
                    'slug'        => 'domain.manage.resources',
                    'description' => 'Manage resources for a domain.',
                ],
                [
                    'name'        => 'Manage domain events',
                    'slug'        => 'domain.manage.events',
                    'description' => 'Create/manage domain-level events.',
                ],

                // Analytics
                [
                    'name'        => 'View analytics',
                    'slug'        => 'analytics.view',
                    'description' => 'View analytics dashboards.',
                ],
                [
                    'name'        => 'Analyze missions',
                    'slug'        => 'analytics.mission',
                    'description' => 'Analyze mission data and produce doctrine.',
                ],
            ];

            $permissionModels = [];
            foreach ($permissions as $data) {
                $permissionModels[$data['slug']] = Permission::updateOrCreate(
                    ['slug' => $data['slug']],
                    $data
                );
            }

            /**
             * -----------------------------------------
             * 3. ROLE → PERMISSION MAPPING
             * -----------------------------------------
             */

            // Director
            $roleModels['director']->permissions()->sync(
                collect($permissionModels)->pluck('id')->all()
            );

            // Member
            $roleModels['member']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['event.create']->id,
                $permissionModels['mission.create']->id,
            ]);

            // Viewer
            $roleModels['viewer']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['user.view']->id,
                $permissionModels['event.create']->id,
                $permissionModels['mission.create']->id,
                $permissionModels['analytics.view']->id,
            ]);

            // Mission Commander
            $roleModels['mission_commander']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['user.view']->id,
                $permissionModels['analytics.view']->id,
                $permissionModels['analytics.mission']->id,
                $permissionModels['mission.view']->id,
            ]);

            // Lieutenant
            $roleModels['lieutenant']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['event.create']->id,
                $permissionModels['event.host.small']->id,
                $permissionModels['mission.create']->id,
                $permissionModels['mission.view']->id,
            ]);

            // Commander – Squadron
            $roleModels['commander_squadron']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['squadron.manage']->id,
                $permissionModels['squadron.members.manage']->id,
                $permissionModels['event.create']->id,
                $permissionModels['event.host.small']->id,
                $permissionModels['event.host.medium']->id,
                $permissionModels['mission.create']->id,
                $permissionModels['mission.manage']->id,
                $permissionModels['mission.view']->id,
                $permissionModels['mission.members.manage']->id,
            ]);

            // Commander – Staff
            $roleModels['commander_staff']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['event.create']->id,
                $permissionModels['event.host.medium']->id,
                $permissionModels['event.host.large']->id,
                $permissionModels['domain.manage.resources']->id,
                $permissionModels['domain.manage.events']->id,
                $permissionModels['analytics.view']->id,
                $permissionModels['mission.view']->id,
            ]);

            // Wing Commander
            $roleModels['wing_commander']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['squadron.manage']->id,
                $permissionModels['squadron.members.manage']->id,
                $permissionModels['event.create']->id,
                $permissionModels['event.host.medium']->id,
                $permissionModels['event.host.large']->id,
                $permissionModels['mission.manage']->id,
                $permissionModels['domain.manage.events']->id,
                $permissionModels['mission.view']->id,
                $permissionModels['mission.members.manage']->id,
            ]);

            // Admiral
            $roleModels['admiral']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['event.create']->id,
                $permissionModels['event.manage']->id,
                $permissionModels['event.host.large']->id,
                $permissionModels['event.host.org']->id,
                $permissionModels['mission.manage']->id,
                $permissionModels['mission.view']->id,
                $permissionModels['mission.members.manage']->id,
                $permissionModels['user.view']->id,
                $permissionModels['domain.manage.resources']->id,
                $permissionModels['domain.manage.events']->id,
                $permissionModels['analytics.view']->id,
            ]);

            // Grand Admiral
            $roleModels['grand_admiral']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['squadron.manage']->id,
                $permissionModels['squadron.members.manage']->id,
                $permissionModels['event.create']->id,
                $permissionModels['event.manage']->id,
                $permissionModels['event.host.large']->id,
                $permissionModels['event.host.org']->id,
                $permissionModels['mission.manage']->id,
                $permissionModels['mission.view']->id,
                $permissionModels['mission.members.manage']->id,
                $permissionModels['user.view']->id,
                $permissionModels['user.manage']->id,
                $permissionModels['domain.manage.resources']->id,
                $permissionModels['domain.manage.events']->id,
                $permissionModels['analytics.view']->id,
                $permissionModels['analytics.mission']->id,
                $permissionModels['system.manage_roles']->id,
                $permissionModels['system.manage_permissions']->id,
                $permissionModels['system.settings']->id,
            ]);

            // Tech Director
            $roleModels['tech_director']->permissions()->sync([
                $permissionModels['user.view']->id,
                $permissionModels['user.manage']->id,
                $permissionModels['system.manage_roles']->id,
                $permissionModels['system.manage_permissions']->id,
                $permissionModels['system.settings']->id,
                $permissionModels['analytics.view']->id,
                $permissionModels['analytics.mission']->id,
                $permissionModels['mission.view']->id,
            ]);

            // Tech Team
            $roleModels['tech_team']->permissions()->sync([
                $permissionModels['user.view']->id,
                $permissionModels['analytics.view']->id,
                $permissionModels['mission.view']->id,
            ]);
        });
    }
}
