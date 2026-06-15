<?php

namespace Tests\Unit\Domain\Operations;

use App\Domain\Operations\OperationRuntimeDiscordService;
use App\Domain\Operations\Services\OperationRuntimeService;
use App\Domain\Operations\Enums\OperationStatus;
use App\Models\Operation;
use App\Models\OperationDiscordChannel;
use App\Models\OperationParticipant;
use App\Models\OperationSyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OperationRuntimeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_build_summarizes_runtime_counts_and_channel_assignments(): void
    {
        $operation = $this->publishedOperation();
        $channel = OperationDiscordChannel::query()->create([
            'operation_id' => $operation->id,
            'name' => 'Hammerheads',
            'sort_order' => 0,
        ]);

        $presentUser = $this->member(['discord_id' => '111']);
        $noShowUser = $this->member();
        $excusedUser = $this->member();
        $signedOffUser = $this->member();
        $walkInUser = $this->member();
        $syncActor = $this->member(['rsi_handle' => 'SyncLead']);

        $presentParticipant = OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $presentUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now(),
            'operation_discord_channel_id' => $channel->id,
        ]);

        OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $noShowUser->id,
            'attendance_status' => 'missed',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_NO_SHOW,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $excusedUser->id,
            'attendance_status' => 'missed',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_EXCUSED,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_MANUAL,
        ]);

        OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $signedOffUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_OFF_BEFORE_START,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'signed_off_at' => now()->subMinutes(10),
        ]);

        OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $walkInUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_WALK_IN,
            'synced_in_at' => now()->subMinutes(5),
        ]);

        $syncRun = OperationSyncRun::query()->create([
            'operation_id' => $operation->id,
            'synced_by_user_id' => $syncActor->id,
            'synced_at' => now()->subMinute(),
            'source_channel_ids' => ['123'],
            'present_user_ids' => [$presentUser->id, $walkInUser->id],
            'no_show_user_ids' => [$noShowUser->id],
            'walk_in_user_ids' => [$walkInUser->id],
        ]);

        $discord = Mockery::mock(OperationRuntimeDiscordService::class);
        $discord->shouldReceive('lobbyChannelIds')->once()->andReturn(['123', '456']);

        $service = new OperationRuntimeService($discord);
        $payload = $service->build($operation->fresh());

        $this->assertSame(['123', '456'], $payload['lobby_channel_ids']);
        $this->assertSame(4, $payload['summary']['active_participant_count']);
        $this->assertSame(2, $payload['summary']['present_count']);
        $this->assertSame(1, $payload['summary']['no_show_count']);
        $this->assertSame(1, $payload['summary']['excused_count']);
        $this->assertSame(1, $payload['summary']['signed_off_count']);
        $this->assertSame(1, $payload['summary']['walk_in_count']);
        $this->assertSame(3, $payload['summary']['missing_discord_link_count']);
        $this->assertSame($syncRun->id, $payload['last_sync_run']['id']);
        $this->assertSame('SyncLead', $payload['last_sync_run']['synced_by']['name']);
        $this->assertSame([$presentParticipant->id], $payload['discord_channels'][0]['assigned_participant_ids']);
    }

    public function test_add_walk_in_updates_latest_sync_run_and_clears_no_show_marker(): void
    {
        $actor = $this->member(['rsi_handle' => 'RuntimeLead']);
        $walkInUser = $this->member();
        $operation = $this->publishedOperation();

        OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $walkInUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_OFF_BEFORE_START,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'signed_off_at' => now()->subMinutes(15),
        ]);

        $latestSyncRun = OperationSyncRun::query()->create([
            'operation_id' => $operation->id,
            'synced_by_user_id' => $actor->id,
            'synced_at' => now()->subMinutes(5),
            'source_channel_ids' => [],
            'present_user_ids' => [99],
            'no_show_user_ids' => [$walkInUser->id, 55],
            'walk_in_user_ids' => [],
        ]);

        $discord = Mockery::mock(OperationRuntimeDiscordService::class);
        $discord->shouldReceive('lobbyChannelIds')->once()->andReturn([]);
        $service = new OperationRuntimeService($discord);

        $payload = $service->addWalkIn($operation, $actor, $walkInUser);

        $participant = OperationParticipant::query()
            ->where('operation_id', $operation->id)
            ->where('user_id', $walkInUser->id)
            ->firstOrFail();

        $this->assertSame(OperationParticipant::RUNTIME_STATUS_SIGNED_UP, $participant->runtime_status);
        $this->assertSame(OperationParticipant::RUNTIME_SOURCE_SIGNED_UP, $participant->runtime_source);
        $this->assertNull($participant->signed_off_at);
        $this->assertNotNull($participant->synced_in_at);

        $latestSyncRun->refresh();

        $presentUserIds = $latestSyncRun->present_user_ids ?? [];
        $expectedPresentUserIds = [99, $walkInUser->id];

        sort($presentUserIds);
        sort($expectedPresentUserIds);

        $this->assertSame($expectedPresentUserIds, $presentUserIds);
        $this->assertSame([55], $latestSyncRun->no_show_user_ids ?? []);
        $this->assertSame([$walkInUser->id], $latestSyncRun->walk_in_user_ids ?? []);
        $this->assertSame($actor->id, $latestSyncRun->synced_by_user_id);
        $this->assertSame(0, $payload['summary']['walk_in_count']);
    }

    public function test_sync_lobby_preserves_excused_technical_issue_and_existing_walk_in_states(): void
    {
        $actor = $this->member(['rsi_handle' => 'Lead']);
        $technicalUser = $this->member(['discord_id' => '111']);
        $excusedUser = $this->member(['discord_id' => '222']);
        $walkInUser = $this->member(['discord_id' => '333']);
        $absentUser = $this->member(['discord_id' => '444']);
        $operation = $this->publishedOperation();

        $technicalParticipant = OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $technicalUser->id,
            'attendance_status' => 'missed',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_TECHNICAL_ISSUE,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_MANUAL,
            'synced_in_at' => now()->subMinutes(20),
        ]);

        $excusedParticipant = OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $excusedUser->id,
            'attendance_status' => 'missed',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_EXCUSED,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_MANUAL,
        ]);

        $walkInParticipant = OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $walkInUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_WALK_IN,
            'synced_in_at' => now()->subMinutes(10),
        ]);

        $absentParticipant = OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $absentUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        $discord = Mockery::mock(OperationRuntimeDiscordService::class);
        $discord->shouldReceive('syncLobby')->once()->with(Mockery::on(fn ($value) => $value->is($operation)))->andReturn([
            'ok' => true,
            'message' => 'Lobby synced.',
            'data' => [
                'source_channel_ids' => ['lobby-1'],
                'present_discord_ids' => ['111'],
                'present_by_channel' => ['lobby-1' => ['111']],
            ],
        ]);
        $discord->shouldReceive('lobbyChannelIds')->once()->andReturn(['lobby-1']);

        $service = new OperationRuntimeService($discord);
        $result = $service->syncLobby($operation, $actor);

        $technicalParticipant->refresh();
        $excusedParticipant->refresh();
        $walkInParticipant->refresh();
        $absentParticipant->refresh();

        $this->assertSame(OperationParticipant::RUNTIME_STATUS_TECHNICAL_ISSUE, $technicalParticipant->runtime_status);
        $this->assertSame(OperationParticipant::RUNTIME_STATUS_EXCUSED, $excusedParticipant->runtime_status);
        $this->assertSame(OperationParticipant::RUNTIME_STATUS_SIGNED_UP, $walkInParticipant->runtime_status);
        $this->assertSame(OperationParticipant::RUNTIME_STATUS_NO_SHOW, $absentParticipant->runtime_status);
        $this->assertSame(1, $result['present_count']);
        $this->assertSame(1, $result['no_show_count']);
        $this->assertSame(0, $result['walk_in_count']);
        $this->assertSame(1, $result['runtime']['summary']['excused_count']);
        $this->assertSame(1, $result['runtime']['summary']['walk_in_count']);
    }

    public function test_cleanup_discord_channels_returns_no_op_when_operation_has_no_runtime_channels(): void
    {
        $operation = $this->publishedOperation();

        $discord = Mockery::mock(OperationRuntimeDiscordService::class);

        $service = new OperationRuntimeService($discord);
        $result = $service->cleanupDiscordChannels($operation);

        $this->assertSame([
            'ok' => true,
            'message' => null,
            'deleted_channel_count' => 0,
            'removed_layout_count' => 0,
        ], $result);
    }

    public function test_cleanup_discord_channels_leaves_layout_and_assignments_untouched_when_discord_cleanup_fails(): void
    {
        $operation = $this->publishedOperation();
        $user = $this->member(['discord_id' => '111']);
        $channel = OperationDiscordChannel::query()->create([
            'operation_id' => $operation->id,
            'name' => 'Hammerheads',
            'sort_order' => 0,
            'discord_channel_id' => '1999',
        ]);

        $participant = OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $user->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'operation_discord_channel_id' => $channel->id,
        ]);

        $discord = Mockery::mock(OperationRuntimeDiscordService::class);
        $discord->shouldReceive('syncOperationChannels')
            ->once()
            ->with(
                Mockery::on(fn ($value) => $value->is($operation)),
                [],
                ['1999']
            )
            ->andReturn([
                'ok' => false,
                'message' => 'Discord bot rejected the request (503).',
                'data' => [],
            ]);

        $service = new OperationRuntimeService($discord);
        $result = $service->cleanupDiscordChannels($operation);

        $participant->refresh();

        $this->assertSame([
            'ok' => false,
            'message' => 'Discord bot rejected the request (503).',
            'deleted_channel_count' => 0,
            'removed_layout_count' => 0,
        ], $result);
        $this->assertDatabaseHas('operation_discord_channels', [
            'id' => $channel->id,
        ]);
        $this->assertSame($channel->id, $participant->operation_discord_channel_id);
    }

    public function test_cleanup_discord_channels_clears_assignments_and_deletes_layout_after_successful_cleanup(): void
    {
        $operation = $this->publishedOperation();
        $user = $this->member(['discord_id' => '111']);
        $channel = OperationDiscordChannel::query()->create([
            'operation_id' => $operation->id,
            'name' => 'Hammerheads',
            'sort_order' => 0,
            'discord_channel_id' => '1999',
        ]);

        $participant = OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $user->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'operation_discord_channel_id' => $channel->id,
        ]);

        $discord = Mockery::mock(OperationRuntimeDiscordService::class);
        $discord->shouldReceive('syncOperationChannels')
            ->once()
            ->with(
                Mockery::on(fn ($value) => $value->is($operation)),
                [],
                ['1999']
            )
            ->andReturn([
                'ok' => true,
                'message' => 'Channels removed.',
                'data' => [
                    'deleted_channel_count' => 1,
                ],
            ]);

        $service = new OperationRuntimeService($discord);
        $result = $service->cleanupDiscordChannels($operation);

        $participant->refresh();

        $this->assertSame([
            'ok' => true,
            'message' => null,
            'deleted_channel_count' => 1,
            'removed_layout_count' => 1,
        ], $result);
        $this->assertDatabaseMissing('operation_discord_channels', [
            'id' => $channel->id,
        ]);
        $this->assertNull($participant->operation_discord_channel_id);
    }

    private function publishedOperation(array $attributes = []): Operation
    {
        $operation = new Operation();
        $operation->forceFill(array_merge([
            'created_by' => $this->member()->id,
            'title' => 'Runtime Service Test',
            'description' => 'Runtime service coverage operation.',
            'visibility' => 'open',
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(4),
            'rsvp_deadline' => now()->addHour(),
            'status' => OperationStatus::Published->value,
            'slots' => [],
        ], $attributes));
        $operation->save();

        return $operation->fresh();
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
        ], $attributes));
    }
}
