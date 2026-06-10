<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverify_clears_all_rows_for_the_same_discord_account(): void
    {
        $this->actingAsDirector();

        $primary = User::factory()->create([
            'discord_id' => 'shared-discord-id',
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'verification_code' => 'ABC-123',
            'verification_expires_at' => now()->addMinutes(10),
            'remember_token' => 'remember-primary',
        ]);

        $duplicate = User::factory()->create([
            'discord_id' => 'shared-discord-id',
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'verification_code' => 'XYZ-789',
            'verification_expires_at' => now()->addMinutes(10),
            'remember_token' => 'remember-duplicate',
        ]);

        $unrelated = User::factory()->create([
            'discord_id' => 'other-discord-id',
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'verification_code' => 'SAFE-111',
            'verification_expires_at' => now()->addMinutes(10),
            'remember_token' => 'remember-safe',
        ]);

        DB::table('sessions')->insert([
            [
                'id' => 'primary-session',
                'user_id' => $primary->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => 'payload',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'duplicate-session',
                'user_id' => $duplicate->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => 'payload',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'unrelated-session',
                'user_id' => $unrelated->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => 'payload',
                'last_activity' => now()->timestamp,
            ],
        ]);

        $response = $this->post(route('admin.users.unverify'), [
            'id' => $primary->id,
        ]);

        $response
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'User marked as unverified.');

        $primary->refresh();
        $duplicate->refresh();
        $unrelated->refresh();

        $this->assertNull($primary->rsi_verified_at);
        $this->assertSame(User::STATUS_PENDING, $primary->global_status);
        $this->assertNull($primary->verification_code);
        $this->assertNull($primary->verification_expires_at);
        $this->assertNull($primary->remember_token);

        $this->assertNull($duplicate->rsi_verified_at);
        $this->assertSame(User::STATUS_PENDING, $duplicate->global_status);
        $this->assertNull($duplicate->verification_code);
        $this->assertNull($duplicate->verification_expires_at);
        $this->assertNull($duplicate->remember_token);

        $this->assertNotNull($unrelated->rsi_verified_at);
        $this->assertSame(User::STATUS_ACTIVE, $unrelated->global_status);
        $this->assertSame('SAFE-111', $unrelated->verification_code);
        $this->assertNotNull($unrelated->verification_expires_at);
        $this->assertSame('remember-safe', $unrelated->remember_token);

        $this->assertDatabaseMissing('sessions', ['id' => 'primary-session']);
        $this->assertDatabaseMissing('sessions', ['id' => 'duplicate-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'unrelated-session']);
    }

    public function test_director_can_clear_all_remember_tokens_and_web_sessions(): void
    {
        $this->actingAsDirector();

        $first = User::factory()->create([
            'remember_token' => 'remember-first',
        ]);
        $second = User::factory()->create([
            'remember_token' => 'remember-second',
        ]);

        DB::table('sessions')->insert([
            [
                'id' => 'first-session',
                'user_id' => $first->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => 'payload',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'second-session',
                'user_id' => $second->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => 'payload',
                'last_activity' => now()->timestamp,
            ],
        ]);

        $response = $this->post(route('admin.users.clearRememberedSessions'));

        $response
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'All remembered logins and web sessions were cleared.');

        $first->refresh();
        $second->refresh();

        $this->assertNull($first->remember_token);
        $this->assertNull($second->remember_token);
        $this->assertDatabaseCount('sessions', 0);
    }

    private function actingAsDirector(array $attributes = []): User
    {
        $directorRole = Role::create([
            'name' => 'Director',
            'slug' => 'director',
            'description' => 'Test director role',
            'is_system' => true,
        ]);

        $user = User::factory()->create(array_merge([
            'global_status' => User::STATUS_ACTIVE,
            'rank' => 'admiral',
            'rank_level' => 5,
            'rsi_verified_at' => now(),
        ], $attributes));

        $user->roles()->attach($directorRole->id);
        $user->load('roles');

        $this->actingAs($user);

        return $user;
    }
}
