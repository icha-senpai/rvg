<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MeWebRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_update_their_profile_via_the_web_me_route(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'rsi_handle' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/user/' . $user->id)
            ->put('/me', [
                'bio' => 'Ready to fly escort and logistics.',
                'timezone' => 'UTC',
                'availability_status' => 'available',
                'loa_note' => 'On most evenings.',
                'site_theme' => 'aegis',
                'favorite_ships' => ['Avenger Titan'],
                'favorite_guns' => ['FS-9'],
                'callsign' => 'Ghost',
                'primary_role' => 'space_combat',
                'secondary_role' => 'medical',
                'preferred_gameplay_style' => 'casual/chill',
                'typical_op_commitment' => 'flexible',
                'experience_ratings' => [
                    'space_combat' => 4,
                    'ground_combat' => 3,
                    'logistics_support' => 5,
                    'medical' => 2,
                ],
            ]);

        $response
            ->assertRedirect('/user/' . $user->id)
            ->assertSessionHas('success', 'Profile updated.');

        $user->refresh();

        $this->assertSame('Ready to fly escort and logistics.', $user->bio);
        $this->assertSame('UTC', $user->timezone);
        $this->assertSame('available', $user->availability_status);
        $this->assertSame('On most evenings.', $user->loa_note);
        $this->assertSame('aegis', $user->site_theme);
        $this->assertSame(['Avenger Titan'], $user->favorite_ships);
        $this->assertSame(['FS-9'], $user->favorite_guns);
        $this->assertSame('Ghost', $user->callsign);
        $this->assertSame('space_combat', $user->primary_role);
        $this->assertSame('medical', $user->secondary_role);
        $this->assertSame('casual/chill', $user->preferred_gameplay_style);
        $this->assertSame('flexible', $user->typical_op_commitment);
        $this->assertSame([
            'space_combat' => 4,
            'ground_combat' => 3,
            'logistics_support' => 5,
            'medical' => 2,
        ], $user->experience_ratings);
    }

    public function test_profile_page_includes_cached_uex_ship_options_for_the_editor(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'rsi_handle' => 'TestPilot',
        ]);

        DB::table('uex_vehicles')->insert([
            [
                'uex_id' => 101,
                'name' => 'Avenger Titan',
                'full_name' => 'Avenger Titan',
                'type' => 'Starter',
                'source_payload' => json_encode(['name' => 'Avenger Titan'], JSON_THROW_ON_ERROR),
                'last_synced_at' => now(),
            ],
            [
                'uex_id' => 202,
                'name' => 'Carrack',
                'full_name' => 'Carrack',
                'type' => 'Expedition',
                'source_payload' => json_encode(['name' => 'Carrack'], JSON_THROW_ON_ERROR),
                'last_synced_at' => now(),
            ],
        ]);

        DB::table('uex_items')->insert([
            [
                'uex_id' => 301,
                'name' => 'FS-9 LMG',
                'type' => 'Weapon',
                'source_payload' => json_encode(['name' => 'FS-9 LMG'], JSON_THROW_ON_ERROR),
                'last_synced_at' => now(),
            ],
            [
                'uex_id' => 302,
                'name' => 'Pembroke Armor',
                'type' => 'Armor',
                'source_payload' => json_encode(['name' => 'Pembroke Armor'], JSON_THROW_ON_ERROR),
                'last_synced_at' => now(),
            ],
        ]);

        $this
            ->actingAs($user)
            ->get(route('member.profile', $user->rsi_handle))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/userpage')
                ->has('favoriteShipOptions', 2)
                ->has('favoriteItemOptions', 2)
                ->where('favoriteShipOptions.0.name', 'Avenger Titan')
                ->where('favoriteShipOptions.0.type', 'Starter')
                ->where('favoriteShipOptions.1.name', 'Carrack')
                ->where('favoriteShipOptions.1.type', 'Expedition')
                ->where('favoriteItemOptions.0.name', 'FS-9 LMG')
                ->where('favoriteItemOptions.0.type', 'Weapon')
                ->where('favoriteItemOptions.1.name', 'Pembroke Armor')
                ->where('favoriteItemOptions.1.type', 'Armor')
            );
    }

    public function test_verified_user_can_open_the_settings_page(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'rsi_handle' => 'SettingsPilot',
            'discord_id' => 'settings-discord-id',
            'discord_name' => 'SettingsPilot',
            'region' => 'US',
            'timezone' => 'America/Chicago',
            'notification_settings' => [
                'operations' => true,
            ],
            'site_theme' => 'mirai',
            'preferred_roles' => ['space_combat'],
            'personal_tags' => ['logistics'],
        ]);

        $this
            ->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Settings')
                ->where('auth.user.notification_settings.operations', true)
                ->where('auth.user.site_theme', 'mirai')
                ->where('auth.user.preferred_roles.0', 'space_combat')
                ->where('auth.user.personal_tags.0', 'logistics')
                ->where('auth.user.region', 'US')
                ->where('auth.user.timezone', 'America/Chicago')
                ->where('discordRoleSettings.discord_linked', true)
                ->has('discordRoleSettings.status')
            );
    }

    public function test_verified_user_can_update_region_and_timezone_from_settings(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'rsi_handle' => 'SettingsPilot',
            'region' => 'EU',
            'timezone' => 'Europe/London',
        ]);

        $this
            ->actingAs($user)
            ->from('/settings')
            ->put(route('me.update'), [
                'region' => 'US',
                'timezone' => 'America/Chicago',
            ])
            ->assertRedirect('/settings')
            ->assertSessionHas('success', 'Profile updated.');

        $user->refresh();

        $this->assertSame('US', $user->region);
        $this->assertSame('America/Chicago', $user->timezone);
    }

    public function test_verified_user_can_sync_discord_self_roles_from_settings(): void
    {
        config()->set('services.discord.guild_id', 'guild-123');
        config()->set('services.discord.bot_token', 'bot-token');

        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'discord_id' => 'discord-123',
            'discord_name' => 'SettingsPilot',
        ]);

        Http::fake([
            'https://discord.com/api/v10/guilds/guild-123/members/discord-123' => Http::response([
                'joined_at' => now()->subMonth()->toIso8601String(),
                'roles' => [
                    '1454412922569621618',
                    '1454412926533373994',
                ],
            ], 200),
        ]);

        $this
            ->actingAs($user)
            ->from('/settings')
            ->post(route('settings.discord-roles.sync'))
            ->assertRedirect('/settings')
            ->assertSessionHas('success', 'Discord roles synced.');

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/guilds/guild-123/members/discord-123')
                && $request->hasHeader('Authorization', 'Bot bot-token');
        });

        $this
            ->actingAs($user)
            ->get('/settings')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Settings')
                ->where('discordRoleSettings.status', 'ready')
                ->where('discordRoleSettings.selected_branch_role_ids', ['1454412922569621618'])
                ->where('discordRoleSettings.selected_player_role_ids.0', '1454412926533373994')
            );
    }

    public function test_verified_user_can_update_discord_self_roles_from_settings(): void
    {
        config()->set('services.discord.guild_id', 'guild-123');
        config()->set('services.discord.bot_token', 'bot-token');

        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'discord_id' => 'discord-123',
            'discord_name' => 'SettingsPilot',
        ]);

        $currentRoles = [
            '999999999999999999',
            '1454412925312696331',
            '1454412926533373994',
        ];

        Http::fake(function ($request) use (&$currentRoles) {
            if ($request->method() === 'GET') {
                return Http::response([
                    'joined_at' => now()->subMonth()->toIso8601String(),
                    'roles' => $currentRoles,
                ], 200);
            }

            if ($request->method() === 'PATCH') {
                $currentRoles = [
                    '999999999999999999',
                    '1454412925312696331',
                    '1454412920963203167',
                    '1454412927758106730',
                    '1454412931159822358',
                ];

                return Http::response([
                    'joined_at' => now()->subMonth()->toIso8601String(),
                    'roles' => $currentRoles,
                ], 200);
            }

            return Http::response([], 404);
        });

        $this
            ->actingAs($user)
            ->from('/settings')
            ->put(route('settings.discord-roles.update'), [
                'branch_role_ids' => [
                    '1454412925312696331',
                    '1454412920963203167',
                ],
                'player_role_ids' => [
                    '1454412927758106730',
                    '1454412931159822358',
                ],
            ])
            ->assertRedirect('/settings')
            ->assertSessionHas('success', 'Discord roles updated.');

        Http::assertSent(function ($request) {
            if ($request->method() !== 'PATCH') {
                return false;
            }

            $body = $request->body();

            return str_contains($request->url(), '/guilds/guild-123/members/discord-123')
                && str_contains($body, '999999999999999999')
                && str_contains($body, '1454412925312696331')
                && str_contains($body, '1454412920963203167')
                && str_contains($body, '1454412927758106730')
                && str_contains($body, '1454412931159822358');
        });

        $this
            ->actingAs($user)
            ->get('/settings')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Settings')
                ->where('discordRoleSettings.selected_branch_role_ids', [
                    '1454412925312696331',
                    '1454412920963203167',
                ])
                ->where('discordRoleSettings.selected_player_role_ids', [
                    '1454412927758106730',
                    '1454412931159822358',
                ])
            );
    }
}
