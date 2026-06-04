<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAndPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_and_admin_roles_receive_expected_ledger_permissions(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $this->assertRoleHasPermissions('lieutenant', [
            'ledger.view-own',
            'ledger.edit-own',
        ]);

        $this->assertRoleHasPermissions('cit', [
            'ledger.view-own',
            'ledger.edit-own',
        ]);

        $this->assertRoleHasPermissions('commander', [
            'ledger.view-own',
            'ledger.edit-own',
        ]);

        $this->assertRoleHasPermissions('wing_commander', [
            'ledger.view-own',
            'ledger.edit-own',
        ]);

        $this->assertRoleHasPermissions('admiral', [
            'ledger.view-own',
            'ledger.edit-own',
            'ledger.view-any',
            'ledger.manage-org-ledger',
        ]);

        $this->assertRoleMissingPermissions('commander', [
            'ledger.manage-squadron-ledger',
        ]);

        $this->assertRoleMissingPermissions('wing_commander', [
            'ledger.manage-squadron-ledger',
        ]);
    }

    protected function assertRoleHasPermissions(string $roleSlug, array $permissionSlugs): void
    {
        $role = Role::query()
            ->where('slug', $roleSlug)
            ->firstOrFail();

        $granted = $role->permissions()
            ->pluck('slug')
            ->all();

        foreach ($permissionSlugs as $permissionSlug) {
            $this->assertContains(
                $permissionSlug,
                $granted,
                "Role [{$roleSlug}] is missing expected permission [{$permissionSlug}]."
            );
        }
    }

    protected function assertRoleMissingPermissions(string $roleSlug, array $permissionSlugs): void
    {
        $role = Role::query()
            ->where('slug', $roleSlug)
            ->firstOrFail();

        $granted = $role->permissions()
            ->pluck('slug')
            ->all();

        foreach ($permissionSlugs as $permissionSlug) {
            $this->assertNotContains(
                $permissionSlug,
                $granted,
                "Role [{$roleSlug}] should not receive permission [{$permissionSlug}] from the seeder."
            );
        }
    }
}
