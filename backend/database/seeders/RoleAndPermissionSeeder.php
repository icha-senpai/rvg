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
                    'description' => 'Junior officer. Can host smaller operations.',
                    'is_system'   => false,
                ],
                [
                    'name'        => 'Commander',
                    'slug'        => 'commander',
                    'description' => 'Manages their squadron and its officers.',
                    'is_system'   => false,
                ],
                [
                    'name'        => 'C.I.T (Commander in Training)',
                    'slug'        => 'cit',
                    'description' => 'Commander in training.',
                    'is_system'   => false,
                ],
                [
                    'name'        => 'Wing Commander',
                    'slug'        => 'wing_commander',
                    'description' => 'Oversees multiple squadrons.',
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
                    'name'        => 'Technical Director',
                    'slug'        => 'tech_director',
                    'description' => 'Manages technical infrastructure and RBAC.',
                    'is_system'   => true,
                ],
                [
                    'name'        => 'Technical Team',
                    'slug'        => 'tech_team',
                    'description' => 'Supports technical operations.',
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

            $legacySquadronCommanderRoleId = Role::query()
                ->where('slug', 'commander_squadron')
                ->value('id');

            if ($legacySquadronCommanderRoleId && isset($roleModels['commander'])) {
                $legacyUserIds = DB::table('role_user')
                    ->where('role_id', $legacySquadronCommanderRoleId)
                    ->pluck('user_id')
                    ->unique()
                    ->values();

                if ($legacyUserIds->isNotEmpty()) {
                    $now = now();
                    $rows = $legacyUserIds
                        ->map(fn ($userId) => [
                            'user_id'    => $userId,
                            'role_id'    => $roleModels['commander']->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                        ->all();

                    DB::table('role_user')->insertOrIgnore($rows);
                }
            }

            $legacyStaffCommanderRoleId = Role::query()
                ->where('slug', 'commander_staff')
                ->value('id');

            if ($legacyStaffCommanderRoleId && isset($roleModels['wing_commander'])) {
                $legacyUserIds = DB::table('role_user')
                    ->where('role_id', $legacyStaffCommanderRoleId)
                    ->pluck('user_id')
                    ->unique()
                    ->values();

                if ($legacyUserIds->isNotEmpty()) {
                    $now = now();
                    $rows = $legacyUserIds
                        ->map(fn ($userId) => [
                            'user_id'    => $userId,
                            'role_id'    => $roleModels['wing_commander']->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                        ->all();

                    DB::table('role_user')->insertOrIgnore($rows);
                }
            }

            Role::query()
                ->whereIn('slug', ['commander_squadron', 'commander_staff', 'mission_commander'])
                ->delete();

            /**
             * -----------------------------------------
             * 2. PERMISSIONS (UNIFIED OPERATIONS ENGINE)
             * -----------------------------------------
             */

            Permission::query()
                ->whereIn('slug', [
                    'operation.host.small',
                    'operation.host.medium',
                    'operation.host.large',
                    'operation.host.org',
                ])
                ->delete();

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

                // OPERATIONS (replaces events + missions entirely)
                [
                    'name'        => 'Create operations',
                    'slug'        => 'operation.create',
                    'description' => 'Create new operations (events or missions).',
                ],
                [
                    'name'        => 'Manage operation stats',
                    'slug'        => 'operation.stats.manage',
                    'description' => 'Adjust operation stats and analytics fields.',
                ],
                [
                    'name'        => 'View operations',
                    'slug'        => 'operation.view',
                    'description' => 'View operation listings and details.',
                ],
                [
                    'name'        => 'Manage operation members',
                    'slug'        => 'operation.members.manage',
                    'description' => 'Add/remove operation participants.',
                ],

                // Operation host tiers
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
                    'description' => 'Manage domain-level resources.',
                ],
                [
                    'name'        => 'Manage domain operations',
                    'slug'        => 'domain.manage.operations',
                    'description' => 'Create/manage domain-level operations.',
                ],

                // Analytics
                [
                    'name'        => 'View analytics',
                    'slug'        => 'analytics.view',
                    'description' => 'View analytics dashboards.',
                ],
                [
                    'name'        => 'Analyze operations',
                    'slug'        => 'analytics.operation',
                    'description' => 'Analyze operational data and help build doctrine.',
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
             * 3. ROLE → PERMISSION MAPPING (UPDATED)
             * -----------------------------------------
             */

            // Director (gets EVERYTHING)
            $roleModels['director']->permissions()->sync(
                collect($permissionModels)->pluck('id')->all()
            );

            // Member
            $roleModels['member']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['operation.view']->id,
            ]);

            // Viewer
            $roleModels['viewer']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['user.view']->id,
                $permissionModels['operation.view']->id,
                $permissionModels['analytics.view']->id,
            ]);

            // Lieutenant
            $roleModels['lieutenant']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['operation.view']->id,
                $permissionModels['operation.create']->id,
                $permissionModels['operation.stats.manage']->id,
            ]);

            // C.I.T (Commander in Training)
            $roleModels['cit']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['operation.view']->id,
                $permissionModels['operation.create']->id,
                $permissionModels['operation.stats.manage']->id,
                $permissionModels['operation.members.manage']->id,
            ]);
            // Commander
            $roleModels['commander']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['squadron.manage']->id,
                $permissionModels['squadron.members.manage']->id,
                $permissionModels['operation.view']->id,
                $permissionModels['operation.create']->id,
                $permissionModels['operation.stats.manage']->id,
                $permissionModels['operation.members.manage']->id,
            ]);

            // Wing Commander
            $roleModels['wing_commander']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['squadron.manage']->id,
                $permissionModels['squadron.members.manage']->id,
                $permissionModels['operation.view']->id,
                $permissionModels['operation.create']->id,
                $permissionModels['operation.stats.manage']->id,
                $permissionModels['operation.members.manage']->id,
                $permissionModels['domain.manage.resources']->id,
                $permissionModels['domain.manage.operations']->id,
                $permissionModels['analytics.view']->id,
            ]);

            // Admiral
            $roleModels['admiral']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['operation.view']->id,
                $permissionModels['operation.create']->id,
                $permissionModels['operation.stats.manage']->id,
                $permissionModels['operation.members.manage']->id,
                $permissionModels['user.view']->id,
                $permissionModels['domain.manage.resources']->id,
                $permissionModels['domain.manage.operations']->id,
                $permissionModels['analytics.view']->id,
            ]);

            // Grand Admiral
            $roleModels['grand_admiral']->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['squadron.manage']->id,
                $permissionModels['squadron.members.manage']->id,
                $permissionModels['operation.view']->id,
                $permissionModels['operation.create']->id,
                $permissionModels['operation.stats.manage']->id,
                $permissionModels['operation.members.manage']->id,
                $permissionModels['user.view']->id,
                $permissionModels['user.manage']->id,
                $permissionModels['domain.manage.resources']->id,
                $permissionModels['domain.manage.operations']->id,
                $permissionModels['analytics.view']->id,
                $permissionModels['analytics.operation']->id,
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
                $permissionModels['analytics.operation']->id,
                $permissionModels['operation.view']->id,
            ]);

            // Tech Team
            $roleModels['tech_team']->permissions()->sync([
                $permissionModels['user.view']->id,
                $permissionModels['analytics.view']->id,
                $permissionModels['operation.view']->id,
            ]);
        });
    }
}
