<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            );
    }
}
