<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminSquadronDiscordWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_create_a_squadron_with_a_manual_discord_channel_and_sync_it(): void
    {
        config()->set('services.bot.url', 'http://localhost:3001/bot');
        config()->set('services.bot.secret', 'bot-secret');
        config()->set('services.discord.squadron_category_id', '1454412955947892842');
        config()->set('services.discord.squadron_shared_role_id', '1509259792072442016');

        Http::fake([
            'http://localhost:3001/bot/squadrons/channel/sync' => Http::response([
                'message' => 'Squadron channel synced.',
            ], 200),
            'http://localhost:3001/bot/squadrons/shared-role/sync-members' => Http::response([
                'message' => 'Shared Squadron role sync completed.',
            ], 200),
        ]);

        $director = $this->makeDirector([
            'discord_id' => '123456789012345678',
            'discord_name' => 'DirectorOne',
        ]);

        $this->actingAs($director)
            ->post(route('admin.squadrons.store'), [
                'name' => 'Mining Squadron',
                'slug' => 'mining-squadron',
                'status' => 'active',
                'branch' => 'industries',
                'division' => 'logistics',
                'leader_id' => $director->id,
                'discord_channel_id' => '145555555555555555',
                'create_discord_channel' => false,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Squadron created and Discord synced.');

        $squadron = Squadron::query()->where('slug', 'mining-squadron')->firstOrFail();

        $this->assertSame('145555555555555555', $squadron->discord_channel_id);
        $this->assertSame(Squadron::DISCORD_STATUS_READY, $squadron->discord_sync_status);
        $this->assertNotNull($squadron->discord_last_synced_at);
        $this->assertNull($squadron->discord_sync_error);

        $this->assertDatabaseHas('squadron_members', [
            'squadron_id' => $squadron->id,
            'user_id' => $director->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_LEADER,
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:3001/bot/squadrons/channel/sync'
                && $request['discord_channel_id'] === '145555555555555555'
                && $request['shared_role_id'] === '1509259792072442016'
                && $request['active_member_discord_ids'] === ['123456789012345678'];
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:3001/bot/squadrons/shared-role/sync-members'
                && $request['shared_role_id'] === '1509259792072442016'
                && $request['member_discord_ids'] === ['123456789012345678']
                && $request['active_squadron_member_discord_ids'] === ['123456789012345678'];
        });
    }

    public function test_squadron_is_still_saved_when_discord_sync_fails(): void
    {
        config()->set('services.bot.url', 'http://localhost:3001/bot');
        config()->set('services.bot.secret', 'bot-secret');
        config()->set('services.discord.squadron_category_id', '1454412955947892842');
        config()->set('services.discord.squadron_shared_role_id', '1509259792072442016');

        Http::fake([
            'http://localhost:3001/bot/squadrons/channel/sync' => Http::response([
                'message' => 'Discord timeout',
            ], 500),
        ]);

        $director = $this->makeDirector();

        $this->actingAs($director)
            ->post(route('admin.squadrons.store'), [
                'name' => 'Logistics Squadron',
                'slug' => 'logistics-squadron',
                'status' => 'active',
                'branch' => 'industries',
                'division' => 'logistics',
                'leader_id' => null,
                'discord_channel_id' => '145666666666666666',
                'create_discord_channel' => false,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Squadron created. Discord setup needs repair: Discord timeout');

        $squadron = Squadron::query()->where('slug', 'logistics-squadron')->firstOrFail();

        $this->assertSame('145666666666666666', $squadron->discord_channel_id);
        $this->assertSame(Squadron::DISCORD_STATUS_REPAIR_NEEDED, $squadron->discord_sync_status);
        $this->assertSame('Discord timeout', $squadron->discord_sync_error);
        $this->assertNull($squadron->discord_last_synced_at);
    }

    public function test_director_can_create_a_discord_channel_for_an_existing_squadron(): void
    {
        config()->set('services.bot.url', 'http://localhost:3001/bot');
        config()->set('services.bot.secret', 'bot-secret');
        config()->set('services.discord.squadron_category_id', '1454412955947892842');
        config()->set('services.discord.squadron_shared_role_id', '1509259792072442016');

        Http::fake([
            'http://localhost:3001/bot/squadrons/channel/create' => Http::response([
                'message' => 'Squadron channel created.',
                'channel_id' => '145777777777777777',
                'channel_name' => 'defence-squadron',
            ], 200),
            'http://localhost:3001/bot/squadrons/channel/sync' => Http::response([
                'message' => 'Squadron channel synced.',
            ], 200),
            'http://localhost:3001/bot/squadrons/shared-role/sync-members' => Http::response([
                'message' => 'Shared Squadron role sync completed.',
            ], 200),
        ]);

        $director = $this->makeDirector();
        $member = User::factory()->create([
            'discord_id' => '223456789012345678',
            'discord_name' => 'PilotTwo',
        ]);

        $squadron = Squadron::query()->create([
            'name' => 'Defence Squadron',
            'slug' => 'defence-squadron',
            'status' => 'active',
            'branch' => 'defence',
            'division' => 'navy',
        ]);

        SquadronMember::query()->create([
            'squadron_id' => $squadron->id,
            'user_id' => $member->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($director)
            ->post(route('admin.squadrons.discord.create-channel'), [
                'id' => $squadron->id,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Discord channel created and squadron channel access synced.');

        $squadron->refresh();

        $this->assertSame('145777777777777777', $squadron->discord_channel_id);
        $this->assertSame(Squadron::DISCORD_STATUS_READY, $squadron->discord_sync_status);
        $this->assertNotNull($squadron->discord_last_synced_at);
        $this->assertNull($squadron->discord_sync_error);

        Http::assertSent(function ($request) use ($squadron) {
            return $request->url() === 'http://localhost:3001/bot/squadrons/channel/create'
                && $request['squadron_id'] === $squadron->id
                && $request['channel_name'] === 'defence-squadron'
                && $request['category_id'] === '1454412955947892842';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:3001/bot/squadrons/channel/sync'
                && $request['discord_channel_id'] === '145777777777777777'
                && $request['active_member_discord_ids'] === ['223456789012345678'];
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:3001/bot/squadrons/shared-role/sync-members'
                && $request['shared_role_id'] === '1509259792072442016'
                && $request['member_discord_ids'] === ['223456789012345678']
                && $request['active_squadron_member_discord_ids'] === ['223456789012345678'];
        });
    }

    public function test_director_can_run_a_full_shared_squadron_role_repair(): void
    {
        config()->set('services.bot.url', 'http://localhost:3001/bot');
        config()->set('services.bot.secret', 'bot-secret');
        config()->set('services.discord.squadron_category_id', '1454412955947892842');
        config()->set('services.discord.squadron_shared_role_id', '1509259792072442016');

        Http::fake([
            'http://localhost:3001/bot/squadrons/shared-role/repair' => Http::response([
                'message' => 'Shared Squadron role repair completed.',
            ], 200),
        ]);

        $director = $this->makeDirector();
        $member = User::factory()->create([
            'discord_id' => '323456789012345678',
        ]);

        $squadron = Squadron::query()->create([
            'name' => 'Medical Squadron',
            'slug' => 'medical-squadron',
            'status' => 'active',
            'branch' => 'lifeline',
            'division' => 'medical',
        ]);

        SquadronMember::query()->create([
            'squadron_id' => $squadron->id,
            'user_id' => $member->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($director)
            ->post(route('admin.squadrons.discord.repair-shared-role'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Shared Squadron role repair completed.');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:3001/bot/squadrons/shared-role/repair'
                && $request['shared_role_id'] === '1509259792072442016'
                && $request['active_squadron_member_discord_ids'] === ['323456789012345678'];
        });
    }

    public function test_sync_channel_access_repairs_a_missing_leader_roster_row_before_syncing(): void
    {
        config()->set('services.bot.url', 'http://localhost:3001/bot');
        config()->set('services.bot.secret', 'bot-secret');
        config()->set('services.discord.squadron_category_id', '1454412955947892842');
        config()->set('services.discord.squadron_shared_role_id', '1509259792072442016');

        Http::fake([
            'http://localhost:3001/bot/squadrons/channel/sync' => Http::response([
                'message' => 'Squadron channel synced.',
            ], 200),
        ]);

        $director = $this->makeDirector([
            'discord_id' => '423456789012345678',
            'discord_name' => 'DirectorRepair',
        ]);

        $squadron = Squadron::query()->create([
            'name' => 'Legacy Medical',
            'slug' => 'legacy-medical',
            'status' => 'active',
            'branch' => 'lifeline',
            'division' => 'medical',
            'leader_id' => $director->id,
            'discord_channel_id' => '1514323015536607262',
        ]);

        $this->assertDatabaseMissing('squadron_members', [
            'squadron_id' => $squadron->id,
            'user_id' => $director->id,
        ]);

        $this->actingAs($director)
            ->post(route('admin.squadrons.discord.sync'), [
                'id' => $squadron->id,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Created the missing leader roster row. Discord squadron channel access synced.');

        $this->assertDatabaseHas('squadron_members', [
            'squadron_id' => $squadron->id,
            'user_id' => $director->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_LEADER,
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:3001/bot/squadrons/channel/sync'
                && $request['discord_channel_id'] === '1514323015536607262'
                && $request['active_member_discord_ids'] === ['423456789012345678'];
        });
    }

    public function test_director_can_delete_a_squadron_without_deleting_its_discord_channel(): void
    {
        config()->set('services.bot.url', 'http://localhost:3001/bot');
        config()->set('services.bot.secret', 'bot-secret');
        config()->set('services.discord.squadron_shared_role_id', '1509259792072442016');

        Http::fake([
            'http://localhost:3001/bot/squadrons/shared-role/sync-members' => Http::response([
                'message' => 'Shared Squadron role sync completed.',
            ], 200),
        ]);

        $director = $this->makeDirector();
        $member = User::factory()->create([
            'discord_id' => '523456789012345678',
        ]);
        $squadron = Squadron::query()->create([
            'name' => 'Archive Squadron',
            'slug' => 'archive-squadron',
            'status' => 'active',
            'discord_channel_id' => '151500000000000000',
        ]);

        SquadronMember::query()->create([
            'squadron_id' => $squadron->id,
            'user_id' => $member->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($director)
            ->post(route('admin.squadrons.delete'), [
                'id' => $squadron->id,
                'delete_discord_channel' => false,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Squadron deleted. Shared Squadron role synced.');

        $this->assertDatabaseMissing('squadrons', [
            'id' => $squadron->id,
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:3001/bot/squadrons/shared-role/sync-members'
                && $request['shared_role_id'] === '1509259792072442016'
                && $request['member_discord_ids'] === ['523456789012345678']
                && $request['active_squadron_member_discord_ids'] === [];
        });
    }

    public function test_director_can_delete_a_squadron_and_its_linked_discord_channel(): void
    {
        config()->set('services.bot.url', 'http://localhost:3001/bot');
        config()->set('services.bot.secret', 'bot-secret');
        config()->set('services.discord.squadron_shared_role_id', '1509259792072442016');

        Http::fake([
            'http://localhost:3001/bot/squadrons/channel/delete' => Http::response([
                'message' => 'Squadron channel deleted.',
                'channel_id' => '151500000000000001',
            ], 200),
            'http://localhost:3001/bot/squadrons/shared-role/sync-members' => Http::response([
                'message' => 'Shared Squadron role sync completed.',
            ], 200),
        ]);

        $director = $this->makeDirector();
        $member = User::factory()->create([
            'discord_id' => '623456789012345678',
        ]);
        $squadron = Squadron::query()->create([
            'name' => 'Delete Squadron',
            'slug' => 'delete-squadron',
            'status' => 'active',
            'discord_channel_id' => '151500000000000001',
        ]);

        SquadronMember::query()->create([
            'squadron_id' => $squadron->id,
            'user_id' => $member->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($director)
            ->post(route('admin.squadrons.delete'), [
                'id' => $squadron->id,
                'delete_discord_channel' => true,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Squadron and linked Discord channel deleted. Shared Squadron role synced.');

        $this->assertDatabaseMissing('squadrons', [
            'id' => $squadron->id,
        ]);

        Http::assertSent(function ($request) use ($squadron) {
            return $request->url() === 'http://localhost:3001/bot/squadrons/channel/delete'
                && $request['squadron_id'] === $squadron->id
                && $request['discord_channel_id'] === '151500000000000001';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:3001/bot/squadrons/shared-role/sync-members'
                && $request['shared_role_id'] === '1509259792072442016'
                && $request['member_discord_ids'] === ['623456789012345678']
                && $request['active_squadron_member_discord_ids'] === [];
        });
    }

    public function test_delete_stops_if_the_linked_discord_channel_cannot_be_deleted(): void
    {
        config()->set('services.bot.url', 'http://localhost:3001/bot');
        config()->set('services.bot.secret', 'bot-secret');

        Http::fake([
            'http://localhost:3001/bot/squadrons/channel/delete' => Http::response([
                'message' => 'Missing permissions',
            ], 500),
        ]);

        $director = $this->makeDirector();
        $squadron = Squadron::query()->create([
            'name' => 'Blocked Delete Squadron',
            'slug' => 'blocked-delete-squadron',
            'status' => 'active',
            'discord_channel_id' => '151500000000000002',
        ]);

        $this->actingAs($director)
            ->post(route('admin.squadrons.delete'), [
                'id' => $squadron->id,
                'delete_discord_channel' => true,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Squadron delete stopped because the linked Discord channel could not be deleted: Missing permissions');

        $this->assertDatabaseHas('squadrons', [
            'id' => $squadron->id,
        ]);
    }

    public function test_delete_reports_when_shared_role_cleanup_after_deletion_fails(): void
    {
        config()->set('services.bot.url', 'http://localhost:3001/bot');
        config()->set('services.bot.secret', 'bot-secret');
        config()->set('services.discord.squadron_shared_role_id', '1509259792072442016');

        Http::fake([
            'http://localhost:3001/bot/squadrons/shared-role/sync-members' => Http::response([
                'message' => 'Role cleanup timeout',
            ], 500),
        ]);

        $director = $this->makeDirector();
        $member = User::factory()->create([
            'discord_id' => '723456789012345678',
        ]);

        $squadron = Squadron::query()->create([
            'name' => 'Cleanup Warning Squadron',
            'slug' => 'cleanup-warning-squadron',
            'status' => 'active',
        ]);

        SquadronMember::query()->create([
            'squadron_id' => $squadron->id,
            'user_id' => $member->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($director)
            ->post(route('admin.squadrons.delete'), [
                'id' => $squadron->id,
                'delete_discord_channel' => false,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Squadron deleted. Shared Squadron role repair needs attention: Role cleanup timeout');

        $this->assertDatabaseMissing('squadrons', [
            'id' => $squadron->id,
        ]);
    }

    protected function makeDirector(array $attributes = []): User
    {
        $director = User::factory()->create(array_merge([
            'rsi_verified_at' => now(),
            'global_status' => User::STATUS_ACTIVE,
        ], $attributes));
        $directorRole = Role::query()->firstOrCreate(
            ['slug' => 'director'],
            ['name' => 'Director']
        );

        $director->roles()->syncWithoutDetaching([$directorRole->id]);

        return $director;
    }
}
