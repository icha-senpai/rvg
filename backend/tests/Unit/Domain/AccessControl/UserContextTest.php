<?php

namespace Tests\Unit\Domain\AccessControl;

use App\Domain\AccessControl\UserContext;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_slug_lookup_is_reused_after_first_check(): void
    {
        $role = Role::create([
            'name' => 'Director',
            'slug' => 'director',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $user->load('roles');

        $context = new class($user) extends UserContext {
            public int $roleCalls = 0;

            public function roles(): \Illuminate\Support\Collection
            {
                $this->roleCalls++;

                return parent::roles();
            }
        };

        $this->assertTrue($context->hasRole('director'));
        $this->assertTrue($context->hasAnyRole(['captain', 'director']));
        $this->assertTrue($context->isDirectorLike());
        $this->assertSame(1, $context->roleCalls);
    }

    public function test_permission_slug_lookup_is_reused_after_first_check(): void
    {
        $role = Role::create([
            'name' => 'Operator',
            'slug' => 'operator',
        ]);

        $permission = Permission::create([
            'name' => 'Manage Operations',
            'slug' => 'operation.manage',
        ]);

        $role->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $user->load('roles.permissions');

        $context = new class($user) extends UserContext {
            public int $permissionCalls = 0;

            public function permissions(): \Illuminate\Support\Collection
            {
                $this->permissionCalls++;

                return parent::permissions();
            }
        };

        $this->assertTrue($context->hasPermission('operation.manage'));
        $this->assertTrue($context->hasAnyPermission(['nope', 'operation.manage']));
        $this->assertFalse($context->hasPermission('operation.delete'));
        $this->assertSame(1, $context->permissionCalls);
    }
}
