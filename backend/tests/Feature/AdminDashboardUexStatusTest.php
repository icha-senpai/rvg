<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\UexSyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardUexStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_like_user_sees_the_uex_dashboard_payload(): void
    {
        $director = $this->directorUser();

        UexSyncRun::create([
            'scope' => 'trade',
            'requested_resources' => ['commodities'],
            'status' => 'success',
            'resource_results' => [[
                'resource' => 'commodities',
                'label' => 'Commodities',
                'group' => 'trade',
                'table' => 'uex_commodities',
                'status' => 'success',
                'records' => 1,
                'error' => null,
            ]],
            'total_records' => 1,
            'successful_resources' => 1,
            'failed_resources' => 0,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

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
            'full_name' => null,
            'display_name' => null,
            'nickname' => null,
            'code' => 'LAR',
            'slug' => 'laranite',
            'type' => null,
            'kind' => null,
            'industry' => null,
            'pad_type' => null,
            'uuid' => null,
            'wiki' => null,
            'game_version' => null,
            'price_buy' => 27.5,
            'price_sell' => 31.75,
            'scu' => null,
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
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('uex.groups', 4)
                ->where('uex.total_rows', 1)
                ->where('uex.commands.all', 'php artisan uex:sync all')
                ->where('uex.sync_actions.all.scope', 'all')
                ->where('uex.last_run.status', 'success')
            );
    }

    public function test_non_director_cannot_open_the_admin_dashboard(): void
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
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_stale_running_uex_run_is_presented_as_failed_on_the_dashboard(): void
    {
        $director = $this->directorUser();

        UexSyncRun::create([
            'scope' => 'all',
            'requested_resources' => ['commodities'],
            'status' => 'running',
            'resource_results' => [],
            'total_records' => 0,
            'successful_resources' => 0,
            'failed_resources' => 0,
            'started_at' => now()->subMinutes(11),
            'finished_at' => null,
        ]);

        $this
            ->actingAs($director)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('uex.last_run.status', 'failed')
                ->where('uex.last_run.error_message', 'Sync did not finish. The process likely crashed or was interrupted before Horizon could save a final status.')
            );
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
