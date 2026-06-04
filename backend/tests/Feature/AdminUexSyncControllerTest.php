<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\UexSyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminUexSyncControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.uex.base_url', 'https://api.uexcorp.uk/2.0');
        config()->set('services.uex.token', 'test-token');
        config()->set('services.uex.timeout', 5);
    }

    public function test_director_can_trigger_a_uex_sync_from_the_admin_dashboard(): void
    {
        Http::fake([
            'https://api.uexcorp.uk/2.0/commodities' => Http::response([
                'status' => 'ok',
                'data' => [[
                    'id' => 101,
                    'name' => 'Laranite',
                    'code' => 'LAR',
                    'slug' => 'laranite',
                    'date_modified' => 1717420800,
                ]],
            ], 200),
        ]);

        $director = $this->directorUser();

        $this
            ->actingAs($director)
            ->post(route('admin.uex.sync'), [
                'scope' => 'all',
                'resources' => ['commodities'],
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('uex_commodities', [
            'uex_id' => 101,
            'name' => 'Laranite',
        ]);

        $run = UexSyncRun::query()->latest('id')->firstOrFail();

        $this->assertSame('success', $run->status);
        $this->assertSame(['commodities'], $run->requested_resources);
    }

    public function test_non_admin_cannot_trigger_uex_sync_from_the_dashboard(): void
    {
        $member = User::factory()->create([
            'discord_id' => 'member-uex-sync',
            'discord_name' => 'Member Sync',
            'rsi_handle' => 'MemberSync',
            'rsi_verified_at' => now(),
            'global_status' => User::STATUS_ACTIVE,
        ]);

        $this
            ->actingAs($member)
            ->post(route('admin.uex.sync'), [
                'scope' => 'all',
                'resources' => ['commodities'],
            ])
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
            'discord_id' => 'director-uex-sync',
            'discord_name' => 'Director Sync',
            'rsi_handle' => 'DirectorSync',
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
