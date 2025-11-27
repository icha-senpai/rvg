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
             * 1. CORE ROLES
             *
             * We keep your existing 'director' and 'member',
             * and add hybrid RBAC roles as slugs.
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

                // Hybrid RBAC roles (rank + function)
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

                // Analyst / Viewer roles
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

                // Tech / infrastructure
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
             * 2. CORE PERMISSIONS
             *
             * We keep your original concepts (squadron.view, event.create, etc)
             * and layer hybrid / tiered permissions on top.
             */
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

                // Events (generic)
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

                // Event tier hosting (hybrid RBAC)
                [
                    'name'        => 'Host small events',
                    'slug'        => 'event.host.small',
                    'description' => 'Host small squad-level events.',
                ],
                [
                    'name'        => 'Host medium events',
                    'slug'        => 'event.host.medium',
                    'description' => 'Host medium events across multiple roles or small multi-squad.',
                ],
                [
                    'name'        => 'Host large events',
                    'slug'        => 'event.host.large',
                    'description' => 'Host large cross-squad or division-wide operations.',
                ],
                [
                    'name'        => 'Host org-wide events',
                    'slug'        => 'event.host.org',
                    'description' => 'Host org-wide strategic operations.',
                ],

                // Missions (generic)
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

                // User & system
                [
                    'name'        => 'View users',
                    'slug'        => 'user.view',
                    'description' => 'View user list and profiles.',
                ],
                [
                    'name'        => 'Manage users',
                    'slug'        => 'user.manage',
                    'description' => 'Edit user data, roles, and status.',
                ],
                [
                    'name'        => 'Manage roles',
                    'slug'        => 'system.manage_roles',
                    'description' => 'Create and assign roles.',
                ],
                [
                    'name'        => 'Manage permissions',
                    'slug'        => 'system.manage_permissions',
                    'description' => 'Create and assign permissions.',
                ],
                [
                    'name'        => 'Manage system settings',
                    'slug'        => 'system.settings',
                    'description' => 'Modify core system settings.',
                ],

                // Domain / division management
                [
                    'name'        => 'Manage domain resources',
                    'slug'        => 'domain.manage.resources',
                    'description' => 'Manage resources for an assigned division or domain.',
                ],
                [
                    'name'        => 'Manage domain events',
                    'slug'        => 'domain.manage.events',
                    'description' => 'Create and manage events for an assigned division or domain.',
                ],

                // Analytics / Mission Commander
                [
                    'name'        => 'View analytics',
                    'slug'        => 'analytics.view',
                    'description' => 'View org-level analytics dashboards.',
                ],
                [
                    'name'        => 'Analyze missions and operations',
                    'slug'        => 'analytics.mission',
                    'description' => 'Review mission data and generate doctrine reports.',
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
             * 3. ASSIGN PERMISSIONS TO ROLES
             *
             * Director: gets everything.
             * Member: base participation.
             * Others: carefully scoped packs.
             */

            // Director = full god mode
            $director = $roleModels['director'];
            $director->permissions()->sync(
                collect($permissionModels)->pluck('id')->all()
            );

            // Member: basic view + can propose events/missions
            $member = $roleModels['member'];
            $member->permissions()->sync([
                $permissionModels['squadron.view']->id,
                $permissionModels['event.create']->id,
                $permissionModels['mission.create']->id,
            ]);

            // Viewer: read-only global view + analytics view
            if (isset($roleModels['viewer'])) {
                $roleModels['viewer']->permissions()->sync([
                    $permissionModels['squadron.view']->id,
                    $permissionModels['user.view']->id,
                    $permissionModels['event.create']->id,    // can propose
                    $permissionModels['mission.create']->id,  // can propose
                    $permissionModels['analytics.view']->id,
                ]);
            }

            // Mission Commander: viewer + mission analytics
            if (isset($roleModels['mission_commander'])) {
                $roleModels['mission_commander']->permissions()->sync([
                    $permissionModels['squadron.view']->id,
                    $permissionModels['user.view']->id,
                    $permissionModels['event.create']->id,
                    $permissionModels['mission.create']->id,
                    $permissionModels['analytics.view']->id,
                    $permissionModels['analytics.mission']->id,
                ]);
            }

            // Lieutenant: small events + basic mission creation
            if (isset($roleModels['lieutenant'])) {
                $roleModels['lieutenant']->permissions()->sync([
                    $permissionModels['squadron.view']->id,
                    $permissionModels['event.create']->id,
                    $permissionModels['event.host.small']->id,
                    $permissionModels['mission.create']->id,
                ]);
            }

            // Commander – Squadron (micro people manager)
            if (isset($roleModels['commander_squadron'])) {
                $roleModels['commander_squadron']->permissions()->sync([
                    $permissionModels['squadron.view']->id,
                    $permissionModels['squadron.manage']->id,
                    $permissionModels['squadron.members.manage']->id,
                    $permissionModels['event.create']->id,
                    $permissionModels['event.host.small']->id,
                    $permissionModels['event.host.medium']->id,
                    $permissionModels['mission.create']->id,
                    $permissionModels['mission.manage']->id,
                ]);
            }

            // Commander – Staff (macro domain manager)
            if (isset($roleModels['commander_staff'])) {
                $roleModels['commander_staff']->permissions()->sync([
                    $permissionModels['squadron.view']->id,
                    $permissionModels['event.create']->id,
                    $permissionModels['event.host.medium']->id,
                    $permissionModels['event.host.large']->id,
                    $permissionModels['domain.manage.resources']->id,
                    $permissionModels['domain.manage.events']->id,
                    $permissionModels['analytics.view']->id,
                ]);
            }

            // Wing Commander: multi-squad & larger events
            if (isset($roleModels['wing_commander'])) {
                $roleModels['wing_commander']->permissions()->sync([
                    $permissionModels['squadron.view']->id,
                    $permissionModels['squadron.manage']->id,
                    $permissionModels['squadron.members.manage']->id,
                    $permissionModels['event.create']->id,
                    $permissionModels['event.host.medium']->id,
                    $permissionModels['event.host.large']->id,
                    $permissionModels['mission.manage']->id,
                    $permissionModels['domain.manage.events']->id,
                ]);
            }

            // Admiral: division-level control + large/org events
            if (isset($roleModels['admiral'])) {
                $roleModels['admiral']->permissions()->sync([
                    $permissionModels['squadron.view']->id,
                    $permissionModels['event.create']->id,
                    $permissionModels['event.manage']->id,
                    $permissionModels['event.host.large']->id,
                    $permissionModels['event.host.org']->id,
                    $permissionModels['mission.manage']->id,
                    $permissionModels['user.view']->id,
                    $permissionModels['domain.manage.resources']->id,
                    $permissionModels['domain.manage.events']->id,
                    $permissionModels['analytics.view']->id,
                ]);
            }

            // Grand Admiral: near-director strategic level
            if (isset($roleModels['grand_admiral'])) {
                $roleModels['grand_admiral']->permissions()->sync([
                    $permissionModels['squadron.view']->id,
                    $permissionModels['squadron.manage']->id,
                    $permissionModels['squadron.members.manage']->id,
                    $permissionModels['event.create']->id,
                    $permissionModels['event.manage']->id,
                    $permissionModels['event.host.large']->id,
                    $permissionModels['event.host.org']->id,
                    $permissionModels['mission.manage']->id,
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
            }

            // Tech Director: RBAC + system controls + analytics
            if (isset($roleModels['tech_director'])) {
                $roleModels['tech_director']->permissions()->sync([
                    $permissionModels['user.view']->id,
                    $permissionModels['user.manage']->id,
                    $permissionModels['system.manage_roles']->id,
                    $permissionModels['system.manage_permissions']->id,
                    $permissionModels['system.settings']->id,
                    $permissionModels['analytics.view']->id,
                    $permissionModels['analytics.mission']->id,
                ]);
            }

            // Tech Team: partial visibility & support
            if (isset($roleModels['tech_team'])) {
                $roleModels['tech_team']->permissions()->sync([
                    $permissionModels['user.view']->id,
                    $permissionModels['analytics.view']->id,
                ]);
            }
        });
    }
}
