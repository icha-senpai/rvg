<?php

namespace Tests\Feature;

use App\Helpers\WebAuthRedirect;
use App\Models\User;
use App\Services\DiscordGuildMembershipService;
use App\Services\DiscordOAuthService;
use App\Services\DiscordUserSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_page_renders_for_guests(): void
    {
        $response = $this->get('/verify');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Verify')
            ->where('verification.discordVerified', false)
            ->where('verification.rsiVerified', false)
            ->where('verification.code', null)
        );
    }

    public function test_verify_page_redirects_home_for_verified_users(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'discord_id' => 'discord-verified-user',
            'rsi_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/verify');

        $response->assertRedirect('/');
    }

    public function test_verify_page_redirects_verified_users_to_their_intended_url(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'discord_id' => 'discord-verified-user-intended',
            'rsi_verified_at' => now(),
        ]);

        $response = $this
            ->withSession([
                WebAuthRedirect::INTENDED_URL_SESSION_KEY => url('/members'),
            ])
            ->actingAs($user)
            ->get('/verify');

        $response
            ->assertRedirect('/members')
            ->assertSessionMissing(WebAuthRedirect::INTENDED_URL_SESSION_KEY);
    }

    public function test_generate_code_requires_authentication(): void
    {
        $response = $this->post('/verify/code');

        $response->assertRedirect('/verify');
    }

    public function test_generate_code_returns_and_persists_a_code_for_an_authenticated_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user)->post('/verify/code');

        $response
            ->assertRedirect(route('verify'))
            ->assertSessionHas('success', 'Your verification code has been generated successfully. It will expire in 10 minutes.');

        $user->refresh();

        $this->assertNotNull($user->verification_code);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{3}-\d{3}$/', $user->verification_code);
        $this->assertNotNull($user->verification_expires_at);
    }

    public function test_verify_page_shows_existing_code_for_an_authenticated_unverified_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_PENDING,
            'discord_id' => 'discord-user-1',
            'verification_code' => 'ABC-123',
            'verification_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->actingAs($user)->get('/verify');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Verify')
            ->where('verification.discordVerified', true)
            ->where('verification.rsiVerified', false)
            ->where('verification.code', 'ABC-123')
        );
    }

    public function test_verify_rsi_requires_authentication(): void
    {
        $response = $this->post('/verify/rsi', [
            'rsi_handle' => 'PilotHandle',
        ]);

        $response->assertRedirect('/verify');
    }

    public function test_verify_rsi_marks_the_authenticated_user_as_verified(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_PENDING,
            'discord_id' => 'discord-user-2',
            'verification_code' => 'ABC-123',
            'verification_expires_at' => now()->addMinutes(10),
        ]);

        Http::fake([
            'https://robertsspaceindustries.com/*' => Http::response('<html><a href="/orgs/SRN">SRN</a><div>ABC-123</div></html>', 200),
        ]);

        $response = $this->actingAs($user)->post('/verify/rsi', [
            'rsi_handle' => 'PilotHandle',
        ]);

        $response
            ->assertRedirect('/')
            ->assertSessionHas('success', 'Account verified successfully!');

        $user->refresh();

        $this->assertSame('PilotHandle', $user->rsi_handle);
        $this->assertNotNull($user->rsi_verified_at);
        $this->assertNull($user->verification_code);
        $this->assertNull($user->verification_expires_at);
    }

    public function test_verify_rsi_redirects_to_their_intended_url_after_success(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_PENDING,
            'discord_id' => 'discord-user-intended-rsi',
            'verification_code' => 'ABC-123',
            'verification_expires_at' => now()->addMinutes(10),
        ]);

        Http::fake([
            'https://robertsspaceindustries.com/*' => Http::response('<html><a href="/orgs/SRN">SRN</a><div>ABC-123</div></html>', 200),
        ]);

        $response = $this
            ->withSession([
                WebAuthRedirect::INTENDED_URL_SESSION_KEY => url('/members'),
            ])
            ->actingAs($user)
            ->post('/verify/rsi', [
                'rsi_handle' => 'PilotHandle',
            ]);

        $response
            ->assertRedirect('/members')
            ->assertSessionHas('success', 'Account verified successfully!')
            ->assertSessionMissing(WebAuthRedirect::INTENDED_URL_SESSION_KEY);
    }

    public function test_discord_redirect_uses_the_oauth_service_redirect(): void
    {
        $this->mock(DiscordOAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('redirect')
                ->once()
                ->andReturn(redirect('https://discord.test/oauth'));
        });

        $response = $this->get('/auth/discord');

        $response->assertRedirect('https://discord.test/oauth');
    }

    public function test_discord_callback_redirects_to_verify_when_auth_succeeds(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'discord_id' => 'discord-user-6',
        ]);

        $discordUser = $this->fakeDiscordUser('discord-user-6', 'Discord User', 'Discord Nick');

        $this->mock(DiscordOAuthService::class, function (MockInterface $mock) use ($discordUser): void {
            $mock->shouldReceive('getUser')
                ->once()
                ->with(true)
                ->andReturn($discordUser);
        });

        $this->mock(DiscordGuildMembershipService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('checkMembership')
                ->once()
                ->with('discord-user-6')
                ->andReturn(true);
        });

        $this->mock(DiscordUserSyncService::class, function (MockInterface $mock) use ($discordUser, $user): void {
            $mock->shouldReceive('syncBasicUser')
                ->once()
                ->with($discordUser)
                ->andReturn($user);
        });

        $response = $this->get('/auth/discord/callback');

        $response
            ->assertRedirect(route('verify'))
            ->assertSessionHas('hz_auth_started_at');

        $this->assertAuthenticatedAs($user);
    }

    public function test_discord_callback_redirects_verified_users_to_their_intended_url(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'discord_id' => 'discord-user-verified-intended',
            'rsi_verified_at' => now(),
        ]);

        $discordUser = $this->fakeDiscordUser('discord-user-verified-intended', 'Discord User', 'Discord Nick');

        $this->mock(DiscordOAuthService::class, function (MockInterface $mock) use ($discordUser): void {
            $mock->shouldReceive('getUser')
                ->once()
                ->with(true)
                ->andReturn($discordUser);
        });

        $this->mock(DiscordGuildMembershipService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('checkMembership')
                ->once()
                ->with('discord-user-verified-intended')
                ->andReturn(true);
        });

        $this->mock(DiscordUserSyncService::class, function (MockInterface $mock) use ($discordUser, $user): void {
            $mock->shouldReceive('syncBasicUser')
                ->once()
                ->with($discordUser)
                ->andReturn($user);
        });

        $response = $this
            ->withSession([
                WebAuthRedirect::INTENDED_URL_SESSION_KEY => url('/members'),
            ])
            ->get('/auth/discord/callback');

        $response
            ->assertRedirect('/members')
            ->assertSessionHas('hz_auth_started_at')
            ->assertSessionMissing(WebAuthRedirect::INTENDED_URL_SESSION_KEY);

        $this->assertAuthenticatedAs($user);
    }

    public function test_discord_callback_falls_back_home_when_intended_url_is_invalid(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'discord_id' => 'discord-user-invalid-intended',
            'rsi_verified_at' => now(),
        ]);

        $discordUser = $this->fakeDiscordUser('discord-user-invalid-intended', 'Discord User', 'Discord Nick');

        $this->mock(DiscordOAuthService::class, function (MockInterface $mock) use ($discordUser): void {
            $mock->shouldReceive('getUser')
                ->once()
                ->with(true)
                ->andReturn($discordUser);
        });

        $this->mock(DiscordGuildMembershipService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('checkMembership')
                ->once()
                ->with('discord-user-invalid-intended')
                ->andReturn(true);
        });

        $this->mock(DiscordUserSyncService::class, function (MockInterface $mock) use ($discordUser, $user): void {
            $mock->shouldReceive('syncBasicUser')
                ->once()
                ->with($discordUser)
                ->andReturn($user);
        });

        $response = $this
            ->withSession([
                WebAuthRedirect::INTENDED_URL_SESSION_KEY => 'https://evil.example/steal-session',
            ])
            ->get('/auth/discord/callback');

        $response
            ->assertRedirect('/')
            ->assertSessionMissing(WebAuthRedirect::INTENDED_URL_SESSION_KEY);
    }

    public function test_protected_web_page_redirects_guests_to_verify_and_stores_intended_url(): void
    {
        $response = $this->get('/members');

        $response
            ->assertRedirect('/verify')
            ->assertSessionHas(WebAuthRedirect::INTENDED_URL_SESSION_KEY, url('/members'));
    }

    public function test_protected_inertia_page_visit_forces_a_verify_location_redirect(): void
    {
        $response = $this
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get('/members');

        $response
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('verify'))
            ->assertSessionHas(WebAuthRedirect::INTENDED_URL_SESSION_KEY, url('/members'));
    }

    public function test_expired_web_session_redirects_to_verify_and_stores_intended_url(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'discord_id' => 'discord-user-expired-session',
            'rsi_verified_at' => now(),
        ]);

        $response = $this
            ->withSession([
                'hz_auth_started_at' => now()->subDays(8)->toIso8601String(),
            ])
            ->actingAs($user)
            ->get('/members');

        $response
            ->assertRedirect('/verify')
            ->assertSessionHas(WebAuthRedirect::INTENDED_URL_SESSION_KEY, url('/members'));

        $this->assertGuest();
    }

    public function test_protected_api_endpoint_returns_json_401_for_guests(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_discord_callback_redirects_to_not_in_guild_when_membership_fails(): void
    {
        $discordUser = $this->fakeDiscordUser('discord-user-7', 'Discord User', 'Discord Nick');

        $this->mock(DiscordOAuthService::class, function (MockInterface $mock) use ($discordUser): void {
            $mock->shouldReceive('getUser')
                ->once()
                ->with(true)
                ->andReturn($discordUser);
        });

        $this->mock(DiscordGuildMembershipService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('checkMembership')
                ->once()
                ->with('discord-user-7')
                ->andReturn(false);
        });

        $this->mock(DiscordUserSyncService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('syncBasicUser');
        });

        $response = $this->get('/auth/discord/callback');

        $response->assertRedirect('/verify?error=not_in_guild');
    }

    public function test_discord_callback_redirects_to_verify_when_guild_check_is_unavailable(): void
    {
        $discordUser = $this->fakeDiscordUser('discord-user-8', 'Discord User', 'Discord Nick');

        $this->mock(DiscordOAuthService::class, function (MockInterface $mock) use ($discordUser): void {
            $mock->shouldReceive('getUser')
                ->once()
                ->with(true)
                ->andReturn($discordUser);
        });

        $this->mock(DiscordGuildMembershipService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('checkMembership')
                ->once()
                ->with('discord-user-8')
                ->andReturn(null);
        });

        $this->mock(DiscordUserSyncService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('syncBasicUser');
        });

        $response = $this->get('/auth/discord/callback');

        $response->assertRedirect('/verify?error=discord_check_unavailable');
    }

    private function fakeDiscordUser(string $id, string $name, ?string $nickname = null): object
    {
        return new class($id, $name, $nickname) {
            public function __construct(
                private readonly string $id,
                private readonly string $name,
                private readonly ?string $nickname,
            ) {}

            public function getId(): string
            {
                return $this->id;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getNickname(): ?string
            {
                return $this->nickname;
            }
        };
    }
}
