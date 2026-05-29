<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_is_a_deprecated_compatibility_endpoint_for_me(): void
    {
        $user = $this->actingAsDirector([
            'discord_id' => 'discord-profile-user',
            'discord_name' => 'Profile User',
            'discord_avatar' => 'avatar.png',
            'rsi_handle' => 'ProfileHandle',
            'rsi_verified_at' => now(),
        ]);

        $profileResponse = $this->getJson('/api/v1/profile');
        $meResponse = $this->getJson('/api/v1/me');

        $profileResponse
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertHeader('X-Deprecated-Endpoint', '/api/v1/profile; use /api/v1/me')
            ->assertJsonPath('data.user.discord_id', 'discord-profile-user')
            ->assertJsonPath('data.user.discord_name', 'Profile User')
            ->assertJsonPath('data.user.discord_avatar', 'avatar.png')
            ->assertJsonPath('data.user.verified_discord', true)
            ->assertJsonPath('data.user.verified_rsi', true)
            ->assertJsonMissingPath('data.user.discord_global_name')
            ->assertJsonMissingPath('data.user.discord_username')
            ->assertJsonMissingPath('data.user.rsi_org');

        $meResponse
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonMissingPath('data.user');

        $this->assertSame($user->id, $profileResponse->json('data.user.id'));
        $this->assertSame($meResponse->json('data.id'), $profileResponse->json('data.user.id'));
        $this->assertSame($meResponse->json('data.discord_id'), $profileResponse->json('data.user.discord_id'));
        $this->assertSame($meResponse->json('data.discord_name'), $profileResponse->json('data.user.discord_name'));
        $this->assertSame($meResponse->json('data.discord_avatar'), $profileResponse->json('data.user.discord_avatar'));
        $this->assertSame($meResponse->json('data.rsi_handle'), $profileResponse->json('data.user.rsi_handle'));
        $this->assertSame($meResponse->json('data.rsi_verified'), $profileResponse->json('data.user.verified_rsi'));
        $this->assertSame(! is_null($meResponse->json('data.discord_id')), $profileResponse->json('data.user.verified_discord'));
        $this->assertSame($meResponse->json('data.rank'), $profileResponse->json('data.user.rank'));
        $this->assertSame($meResponse->json('data.rank_level'), $profileResponse->json('data.user.rank_level'));
        $this->assertSame($meResponse->json('data.rank_name'), $profileResponse->json('data.user.rank_name'));
    }

    public function test_verified_users_endpoint_uses_discord_id_and_rsi_verified_at(): void
    {
        $this->actingAsDirector();

        $verifiedUser = User::factory()->create([
            'discord_id' => 'discord-verified-user',
            'discord_name' => 'Verified User',
            'rsi_verified_at' => now(),
        ]);

        $missingDiscord = User::factory()->create([
            'discord_id' => null,
            'rsi_verified_at' => now(),
        ]);

        $missingRsi = User::factory()->create([
            'discord_id' => 'discord-unverified-user',
            'rsi_verified_at' => null,
        ]);

        $response = $this->getJson('/api/v1/users/verified');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $userIds = array_column($response->json('data.users') ?? [], 'id');

        $this->assertContains($verifiedUser->id, $userIds);
        $this->assertNotContains($missingDiscord->id, $userIds);
        $this->assertNotContains($missingRsi->id, $userIds);
    }

    public function test_unverified_users_endpoint_uses_discord_id_and_rsi_verified_at(): void
    {
        $this->actingAsDirector();

        $verifiedUser = User::factory()->create([
            'discord_id' => 'discord-verified-user',
            'discord_name' => 'Verified User',
            'rsi_verified_at' => now(),
        ]);

        $missingDiscord = User::factory()->create([
            'discord_id' => null,
            'rsi_verified_at' => now(),
        ]);

        $missingRsi = User::factory()->create([
            'discord_id' => 'discord-unverified-user',
            'rsi_verified_at' => null,
        ]);

        $response = $this->getJson('/api/v1/users/unverified');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $userIds = array_column($response->json('data.users') ?? [], 'id');

        $this->assertNotContains($verifiedUser->id, $userIds);
        $this->assertContains($missingDiscord->id, $userIds);
        $this->assertContains($missingRsi->id, $userIds);
    }

    private function actingAsDirector(array $attributes = []): User
    {
        $directorRole = Role::create([
            'name' => 'Director',
            'slug' => 'director',
            'description' => 'Test director role',
            'is_system' => true,
        ]);

        $manageUsersPermission = Permission::create([
            'name' => 'Manage users',
            'slug' => 'user.manage',
            'description' => 'Manage user data, roles, and status.',
        ]);

        $directorRole->permissions()->attach($manageUsersPermission->id);

        $user = User::factory()->create(array_merge([
            'global_status' => User::STATUS_ACTIVE,
            'rank' => 'admiral',
            'rank_level' => 5,
        ], $attributes));

        $user->roles()->attach($directorRole->id);
        $user->load('roles');

        Sanctum::actingAs($user, ['access']);

        return $user;
    }
}
