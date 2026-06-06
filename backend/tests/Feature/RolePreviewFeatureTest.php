<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RolePreviewFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_preview_the_app_as_a_different_role(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $director = $this->directorUser();

        $this
            ->actingAs($director)
            ->from(route('members.index'))
            ->post(route('role-preview.update'), [
                'role_slug' => 'member',
            ])
            ->assertRedirect(route('members.index'));

        $this
            ->actingAs($director)
            ->get(route('members.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Index')
                ->where('auth.user.rank', 'member')
                ->where('auth.user.rank_level', 1)
                ->where('auth.user.roles.0.slug', 'member')
                ->where('auth.can', function ($permissions) {
                    $permissions = collect($permissions)->all();

                    return ($permissions['user.manage'] ?? null) === false
                        && ($permissions['ledger.view-own'] ?? null) === true;
                })
                ->where('rolePreview.canManage', true)
                ->where('rolePreview.activeRoleSlug', 'member')
                ->where('rolePreview.activeRoleLabel', 'Member')
            );
    }

    public function test_non_director_cannot_change_role_preview(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $member = $this->memberUser();

        $this
            ->actingAs($member)
            ->post(route('role-preview.update'), [
                'role_slug' => 'commander',
            ])
            ->assertForbidden();
    }

    public function test_previewed_member_no_longer_gets_director_only_org_ledger_access(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $director = $this->directorUser();

        $this
            ->actingAs($director)
            ->post(route('role-preview.update'), [
                'role_slug' => 'member',
            ])
            ->assertRedirect();

        $this
            ->actingAs($director)
            ->get(route('organization.ledger'))
            ->assertForbidden();

        $this
            ->actingAs($director)
            ->post(route('role-preview.update'), [
                'role_slug' => null,
            ])
            ->assertRedirect();

        $this
            ->actingAs($director)
            ->get(route('organization.ledger'))
            ->assertOk();
    }

    protected function directorUser(): User
    {
        $role = Role::query()->where('slug', 'director')->firstOrFail();

        $user = User::factory()->create([
            'discord_id' => 'role-preview-director',
            'discord_name' => 'Role Preview Director',
            'rsi_handle' => 'RolePreviewDirector',
            'rank' => 'director',
            'rank_level' => 8,
            'rsi_verified_at' => now(),
            'global_status' => User::STATUS_ACTIVE,
        ]);

        $user->roles()->attach($role->id);
        $user->load('roles');

        return $user;
    }

    protected function memberUser(): User
    {
        $role = Role::query()->where('slug', 'member')->firstOrFail();

        $user = User::factory()->create([
            'discord_id' => 'role-preview-member',
            'discord_name' => 'Role Preview Member',
            'rsi_handle' => 'RolePreviewMember',
            'rank' => 'member',
            'rank_level' => 1,
            'rsi_verified_at' => now(),
            'global_status' => User::STATUS_ACTIVE,
        ]);

        $user->roles()->attach($role->id);
        $user->load('roles');

        return $user;
    }
}
