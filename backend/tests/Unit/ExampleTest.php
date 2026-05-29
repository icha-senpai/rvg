<?php

namespace Tests\Unit;

use App\Domain\AccessControl\RoleHierarchy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Role hierarchy stays the source of truth for rank-level comparisons.
     */
    public function test_role_hierarchy_compares_user_rank_by_highest_role(): void
    {
        $user = User::factory()->create();
        $member = Role::create(['name' => 'Member', 'slug' => 'member']);
        $commander = Role::create(['name' => 'Commander', 'slug' => 'commander']);

        $user->roles()->attach([$member->id, $commander->id]);
        $user->load('roles');

        $this->assertTrue(RoleHierarchy::userAtLeast($user, 'member'));
        $this->assertTrue(RoleHierarchy::userAtLeast($user, 'commander'));
        $this->assertFalse(RoleHierarchy::userAtLeast($user, 'admiral'));
    }
}
