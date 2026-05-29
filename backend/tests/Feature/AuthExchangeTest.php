<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_guests_to_verify(): void
    {
        $response = $this->get('/login');

        $response->assertRedirect('/verify');
    }

    public function test_login_redirects_unverified_authenticated_users_to_verify(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect('/verify');
    }

    public function test_login_redirects_verified_authenticated_users_home(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'discord_id' => 'discord-auth-test-user',
            'rsi_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect('/');
    }
}
