<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUexDataControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_browse_a_synced_uex_resource(): void
    {
        $director = $this->directorUser();

        DB::table('uex_commodities')->insert([
            'uex_id' => 101,
            'parent_uex_id' => null,
            'category_uex_id' => null,
            'item_uex_id' => null,
            'company_uex_id' => null,
            'faction_uex_id' => null,
            'jurisdiction_uex_id' => null,
            'star_system_uex_id' => null,
            'planet_uex_id' => null,
            'orbit_uex_id' => null,
            'moon_uex_id' => null,
            'space_station_uex_id' => null,
            'city_uex_id' => null,
            'outpost_uex_id' => null,
            'poi_uex_id' => null,
            'terminal_uex_id' => null,
            'vehicle_uex_id' => null,
            'commodity_uex_id' => null,
            'name' => 'Laranite',
            'full_name' => 'Laranite',
            'display_name' => null,
            'nickname' => null,
            'code' => 'LAR',
            'slug' => 'laranite',
            'type' => 'mineral',
            'kind' => null,
            'industry' => null,
            'pad_type' => null,
            'uuid' => null,
            'wiki' => null,
            'game_version' => '4.1',
            'price_buy' => 27.5,
            'price_sell' => 31.75,
            'scu' => 1,
            'mass' => null,
            'width' => null,
            'height' => null,
            'length' => null,
            'fuel_quantum' => null,
            'fuel_hydrogen' => null,
            'is_available' => true,
            'is_available_live' => true,
            'is_visible' => true,
            'is_default' => null,
            'is_item_manufacturer' => null,
            'is_vehicle_manufacturer' => null,
            'is_buyable' => true,
            'is_sellable' => true,
            'is_illegal' => false,
            'is_ground_vehicle' => null,
            'is_spaceship' => null,
            'is_cargo' => null,
            'is_mining' => null,
            'is_refinery' => null,
            'is_medical' => null,
            'source_payload' => json_encode(['id' => 101, 'name' => 'Laranite'], JSON_THROW_ON_ERROR),
            'source_modified_at' => now(),
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($director)
            ->getJson(route('admin.uex.resources.index', ['resource' => 'commodities', 'search' => 'lara']))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('payload.resource', 'commodities')
            ->assertJsonPath('payload.table', 'uex_commodities')
            ->assertJsonPath('payload.rows.total', 1)
            ->assertJsonPath('payload.rows.data.0.name', 'Laranite');
    }

    public function test_non_director_cannot_browse_uex_admin_resource_data(): void
    {
        $member = User::factory()->create([
            'discord_id' => 'member-discord',
            'discord_name' => 'Member User',
            'rsi_handle' => 'MemberUser',
            'rsi_verified_at' => now(),
            'global_status' => User::STATUS_ACTIVE,
        ]);

        $this
            ->actingAs($member)
            ->getJson(route('admin.uex.resources.index', ['resource' => 'commodities']))
            ->assertForbidden();
    }

    protected function directorUser(): User
    {
        $role = Role::create([
            'name' => 'Director',
            'slug' => 'director',
            'description' => 'Admin dashboard access',
            'is_system' => true,
        ]);

        $user = User::factory()->create([
            'discord_id' => 'director-discord',
            'discord_name' => 'Director User',
            'rsi_handle' => 'DirectorUser',
            'rank' => 'director',
            'rank_level' => 8,
            'rsi_verified_at' => now(),
            'global_status' => User::STATUS_ACTIVE,
        ]);

        $user->roles()->attach($role->id);
        $user->load('roles');

        return $user;
    }
}
