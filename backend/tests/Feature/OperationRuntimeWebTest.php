<?php

namespace Tests\Feature;

use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationRuntimeWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_after_rsvp_deadline_marks_participant_signed_off_before_start(): void
    {
        $creator = $this->verifiedUser();
        $participant = $this->verifiedUser();

        $operation = $this->publishedOperation($creator, [
            'rsvp_deadline' => now()->subMinutes(5),
            'starts_at' => now()->addHour(),
        ]);

        $rosterRow = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $participant->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        $this->actingAs($participant)
            ->from('/operations/' . $operation->id)
            ->post('/operations/' . $operation->id . '/leave')
            ->assertRedirect('/operations/' . $operation->id)
            ->assertSessionHas('success', 'Signed off before start.');

        $rosterRow->refresh();

        $this->assertSame(OperationParticipant::RUNTIME_STATUS_SIGNED_OFF_BEFORE_START, $rosterRow->runtime_status);
        $this->assertNotNull($rosterRow->signed_off_at);
    }

    public function test_runtime_sync_marks_present_no_show_and_walk_in_members(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_lobby_1_channel_id', '1454413000650788949');
        config()->set('services.discord.operation_lobby_2_channel_id', '1454413003142332448');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-lobby' => Http::response([
                'message' => 'Operation lobby sync completed.',
                'source_channel_ids' => ['1454413000650788949', '1454413003142332448'],
                'present_discord_ids' => ['111', '333'],
                'present_by_channel' => [
                    '1454413000650788949' => ['111'],
                    '1454413003142332448' => ['333'],
                ],
            ]),
        ]);

        $creator = $this->verifiedUser();
        $presentUser = $this->verifiedUser(['discord_id' => '111']);
        $noShowUser = $this->verifiedUser(['discord_id' => '222']);
        $walkInUser = $this->verifiedUser(['discord_id' => '333']);

        $operation = $this->publishedOperation($creator, [
            'rsvp_deadline' => now()->subMinutes(15),
            'starts_at' => now()->addHour(),
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $presentUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $noShowUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/run/sync')
            ->assertRedirect()
            ->assertSessionHas('success', 'Lobby synced. 2 present, 1 not here, 1 walk-in.');

        $this->assertDatabaseHas('operation_participants', [
            'operation_id' => $operation->id,
            'user_id' => $presentUser->id,
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        $this->assertDatabaseHas('operation_participants', [
            'operation_id' => $operation->id,
            'user_id' => $noShowUser->id,
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_NO_SHOW,
        ]);

        $this->assertDatabaseHas('operation_participants', [
            'operation_id' => $operation->id,
            'user_id' => $walkInUser->id,
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_WALK_IN,
        ]);

        $this->assertDatabaseHas('operation_sync_runs', [
            'operation_id' => $operation->id,
            'synced_by_user_id' => $creator->id,
        ]);
    }

    public function test_runtime_sync_filters_sparse_discord_ids_without_corrupting_local_state(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_lobby_1_channel_id', '1454413000650788949');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-lobby' => Http::response([
                'message' => 'Operation lobby sync completed.',
                'present_discord_ids' => ['111', '', '333', null, '111', '   '],
            ]),
        ]);

        $creator = $this->verifiedUser();
        $presentUser = $this->verifiedUser(['discord_id' => '111']);
        $noShowUser = $this->verifiedUser(['discord_id' => '222']);
        $walkInUser = $this->verifiedUser(['discord_id' => '333']);

        $operation = $this->publishedOperation($creator, [
            'rsvp_deadline' => now()->subMinutes(15),
            'starts_at' => now()->addHour(),
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $presentUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $noShowUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/run/sync')
            ->assertRedirect()
            ->assertSessionHas('success', 'Lobby synced. 2 present, 1 not here, 1 walk-in.');

        $syncRun = $operation->fresh()->syncRuns()->latest('id')->firstOrFail();

        $this->assertSame([], $syncRun->source_channel_ids);

        $presentUserIds = $syncRun->present_user_ids;
        sort($presentUserIds);
        $expectedPresentUserIds = [$presentUser->id, $walkInUser->id];
        sort($expectedPresentUserIds);

        $this->assertSame($expectedPresentUserIds, $presentUserIds);
        $this->assertSame([$noShowUser->id], $syncRun->no_show_user_ids);
        $this->assertSame([$walkInUser->id], $syncRun->walk_in_user_ids);

        $this->assertDatabaseCount('operation_participants', 3);
    }

    public function test_run_page_renders_runtime_summary_for_operation_manager(): void
    {
        $creator = $this->verifiedUser();
        $activeUser = $this->verifiedUser(['discord_id' => '555']);
        $signedOffUser = $this->verifiedUser(['discord_id' => '556']);

        $operation = $this->publishedOperation($creator);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $activeUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now(),
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $signedOffUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_OFF_BEFORE_START,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'signed_off_at' => now(),
        ]);

        $this->actingAs($creator)
            ->get('/operations/' . $operation->id . '/run')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/OperationRun')
                ->where('operation.participants_count', 1)
                ->where('runtime.summary.present_count', 1)
                ->where('runtime.summary.signed_off_count', 1)
            );
    }

    public function test_run_tool_can_save_funds_prep_draft(): void
    {
        $creator = $this->verifiedUser();
        $participantUser = $this->verifiedUser(['discord_id' => '654']);

        $operation = $this->publishedOperation($creator);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $participantUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now(),
        ]);

        $this->actingAs($creator)
            ->put('/operations/' . $operation->id . '/run/funds-prep', [
                'money_rows' => [
                    [
                        'row_key' => 'prep-1',
                        'recipient_type' => 'member',
                        'recipient_user_id' => $participantUser->id,
                        'amount' => 125000,
                        'notes' => 'Early payout draft',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Funds prep draft saved.');

        $this->assertDatabaseHas('operation_settlements', [
            'operation_id' => $operation->id,
            'prep_money_rows_updated_by_user_id' => $creator->id,
        ]);

        $this->actingAs($creator)
            ->get('/operations/' . $operation->id . '/run')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/OperationRun')
                ->where('fundsPrep.money_rows.0.amount', 125000)
                ->where('fundsPrep.money_rows.0.notes', 'Early payout draft')
                ->where('fundsPrep.eligible_recipients.0.recipient_type', 'organization')
            );
    }

    public function test_run_tool_can_update_participant_runtime_status_manually(): void
    {
        $creator = $this->verifiedUser();
        $participantUser = $this->verifiedUser(['discord_id' => '777']);

        $operation = $this->publishedOperation($creator);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $participantUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_NO_SHOW,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/run/participants/' . $participant->id, [
                'status' => 'operation_finished',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Runtime status updated.');

        $participant->refresh();

        $this->assertSame(OperationParticipant::RUNTIME_STATUS_OPERATION_FINISHED, $participant->runtime_status);
        $this->assertSame('attended', $participant->attendance_status);
        $this->assertNotNull($participant->synced_in_at);
    }

    public function test_run_tool_can_restore_signed_off_participant_to_present_and_clear_signed_off_timestamp(): void
    {
        $creator = $this->verifiedUser();
        $participantUser = $this->verifiedUser(['discord_id' => '777-restore']);

        $operation = $this->publishedOperation($creator);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $participantUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_OFF_BEFORE_START,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'signed_off_at' => now()->subMinutes(15),
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/run/participants/' . $participant->id, [
                'status' => 'present',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Runtime status updated.');

        $participant->refresh();

        $this->assertSame(OperationParticipant::RUNTIME_STATUS_SIGNED_UP, $participant->runtime_status);
        $this->assertNull($participant->signed_off_at);
        $this->assertNotNull($participant->synced_in_at);
    }

    public function test_run_tool_can_mark_a_participant_excused(): void
    {
        $creator = $this->verifiedUser();
        $participantUser = $this->verifiedUser(['discord_id' => '778']);

        $operation = $this->publishedOperation($creator);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $participantUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_NO_SHOW,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/run/participants/' . $participant->id, [
                'status' => 'excused',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Runtime status updated.');

        $participant->refresh();

        $this->assertSame(OperationParticipant::RUNTIME_STATUS_EXCUSED, $participant->runtime_status);
        $this->assertSame('missed', $participant->attendance_status);
        $this->assertNull($participant->synced_in_at);
    }

    public function test_run_tool_can_add_walk_in_member_directly(): void
    {
        $creator = $this->verifiedUser();
        $walkInUser = $this->verifiedUser(['discord_id' => '888']);

        $operation = $this->publishedOperation($creator);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/run/walk-ins', [
                'user_id' => $walkInUser->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Walk-in added to the live roster.');

        $this->assertDatabaseHas('operation_participants', [
            'operation_id' => $operation->id,
            'user_id' => $walkInUser->id,
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_WALK_IN,
        ]);

        $this->assertDatabaseHas('operation_sync_runs', [
            'operation_id' => $operation->id,
            'synced_by_user_id' => $creator->id,
        ]);

        $this->actingAs($creator)
            ->get('/operations/' . $operation->id . '/run')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/OperationRun')
                ->where('runtime.last_sync_run.present_user_ids.0', $walkInUser->id)
                ->where('runtime.last_sync_run.walk_in_user_ids.0', $walkInUser->id)
            );
    }

    public function test_run_tool_cannot_add_member_already_on_live_roster_as_walk_in(): void
    {
        $creator = $this->verifiedUser();
        $participantUser = $this->verifiedUser(['discord_id' => '888-duplicate']);

        $operation = $this->publishedOperation($creator);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $participantUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now(),
        ]);

        $this->actingAs($creator)
            ->from('/operations/' . $operation->id . '/run')
            ->post('/operations/' . $operation->id . '/run/walk-ins', [
                'user_id' => $participantUser->id,
            ])
            ->assertRedirect('/operations/' . $operation->id . '/run')
            ->assertSessionHasErrors('user_id');
    }

    public function test_run_tool_cannot_update_participant_from_another_operation(): void
    {
        $creator = $this->verifiedUser();
        $participantUser = $this->verifiedUser(['discord_id' => 'foreign-row']);

        $operation = $this->publishedOperation($creator);
        $otherOperation = $this->publishedOperation($creator, ['title' => 'Other Operation']);

        $foreignParticipant = OperationParticipant::create([
            'operation_id' => $otherOperation->id,
            'user_id' => $participantUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        $this->actingAs($creator)
            ->from('/operations/' . $operation->id . '/run')
            ->post('/operations/' . $operation->id . '/run/participants/' . $foreignParticipant->id, [
                'status' => 'no_show',
            ])
            ->assertRedirect('/operations/' . $operation->id . '/run')
            ->assertSessionHasErrors('participant');
    }

    public function test_run_tool_sync_is_blocked_for_completed_operations(): void
    {
        $creator = $this->verifiedUser();
        $operation = $this->publishedOperation($creator, [
            'status' => 'completed',
        ]);

        $this->actingAs($creator)
            ->from('/operations/' . $operation->id . '/run')
            ->post('/operations/' . $operation->id . '/run/sync')
            ->assertRedirect('/operations/' . $operation->id . '/run')
            ->assertSessionHasErrors('operation');
    }

    public function test_run_tool_funds_prep_is_blocked_after_operation_is_completed(): void
    {
        $creator = $this->verifiedUser();
        $participantUser = $this->verifiedUser(['discord_id' => '654-completed-prep']);

        $operation = $this->publishedOperation($creator, [
            'status' => 'completed',
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $participantUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now(),
        ]);

        $this->actingAs($creator)
            ->from('/operations/' . $operation->id . '/run')
            ->put('/operations/' . $operation->id . '/run/funds-prep', [
                'money_rows' => [
                    [
                        'row_key' => 'prep-1',
                        'recipient_type' => 'member',
                        'recipient_user_id' => $participantUser->id,
                        'amount' => 125000,
                    ],
                ],
            ])
            ->assertRedirect('/operations/' . $operation->id . '/run')
            ->assertSessionHasErrors('operation');
    }

    public function test_runtime_sync_preserves_finished_participants_when_they_leave_the_lobby(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_lobby_1_channel_id', '1454413000650788949');
        config()->set('services.discord.operation_lobby_2_channel_id', '1454413003142332448');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-lobby' => Http::response([
                'message' => 'Operation lobby sync completed.',
                'source_channel_ids' => ['1454413000650788949', '1454413003142332448'],
                'present_discord_ids' => [],
                'present_by_channel' => [],
            ]),
        ]);

        $creator = $this->verifiedUser();
        $participantUser = $this->verifiedUser(['discord_id' => '999']);

        $operation = $this->publishedOperation($creator, [
            'rsvp_deadline' => now()->subMinutes(15),
            'starts_at' => now()->addHour(),
        ]);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $participantUser->id,
            'attendance_status' => 'attended',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_OPERATION_FINISHED,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/run/sync')
            ->assertRedirect();

        $participant->refresh();

        $this->assertSame(OperationParticipant::RUNTIME_STATUS_OPERATION_FINISHED, $participant->runtime_status);
        $this->assertNotNull($participant->synced_in_at);
    }

    public function test_runtime_sync_preserves_excused_participants_when_they_remain_absent(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_lobby_1_channel_id', '1454413000650788949');
        config()->set('services.discord.operation_lobby_2_channel_id', '1454413003142332448');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-lobby' => Http::response([
                'message' => 'Operation lobby sync completed.',
                'source_channel_ids' => ['1454413000650788949', '1454413003142332448'],
                'present_discord_ids' => [],
                'present_by_channel' => [],
            ]),
        ]);

        $creator = $this->verifiedUser();
        $participantUser = $this->verifiedUser(['discord_id' => '1001']);

        $operation = $this->publishedOperation($creator, [
            'rsvp_deadline' => now()->subMinutes(15),
            'starts_at' => now()->addHour(),
        ]);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $participantUser->id,
            'attendance_status' => 'missed',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_EXCUSED,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_MANUAL,
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/run/sync')
            ->assertRedirect();

        $participant->refresh();

        $this->assertSame(OperationParticipant::RUNTIME_STATUS_EXCUSED, $participant->runtime_status);
        $this->assertNull($participant->synced_in_at);
    }

    public function test_runtime_sync_failure_does_not_mutate_roster_statuses(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_lobby_1_channel_id', '1454413000650788949');
        config()->set('services.discord.operation_lobby_2_channel_id', '1454413003142332448');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-lobby' => Http::response([
                'message' => 'Discord bot is unavailable right now.',
            ], 500),
        ]);

        $creator = $this->verifiedUser();
        $participantUser = $this->verifiedUser(['discord_id' => 'sync-fail-1']);

        $operation = $this->publishedOperation($creator, [
            'rsvp_deadline' => now()->subMinutes(15),
            'starts_at' => now()->addHour(),
        ]);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $participantUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        $this->actingAs($creator)
            ->from('/operations/' . $operation->id . '/run')
            ->post('/operations/' . $operation->id . '/run/sync')
            ->assertRedirect('/operations/' . $operation->id . '/run')
            ->assertSessionHasErrors('discord');

        $participant->refresh();

        $this->assertSame(OperationParticipant::RUNTIME_STATUS_SIGNED_UP, $participant->runtime_status);
        $this->assertNull($participant->synced_in_at);
        $this->assertDatabaseCount('operation_sync_runs', 0);
    }

    public function test_operation_channel_layout_can_be_saved_from_run_tool(): void
    {
        $creator = $this->verifiedUser();
        $activeUser = $this->verifiedUser(['discord_id' => '555']);

        $operation = $this->publishedOperation($creator);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $activeUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now(),
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/run/layout', [
                'channels' => [
                    [
                        'client_key' => 'draft-1',
                        'name' => 'Hammer Team',
                    ],
                ],
                'participant_assignments' => [
                    [
                        'participant_id' => $participant->id,
                        'channel_key' => 'draft-1',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Operation Discord channel layout saved.');

        $this->assertDatabaseHas('operation_discord_channels', [
            'operation_id' => $operation->id,
            'name' => 'Hammer Team',
        ]);

        $participant->refresh();
        $this->assertNotNull($participant->operation_discord_channel_id);

        $this->actingAs($creator)
            ->get('/operations/' . $operation->id . '/run')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/OperationRun')
                ->where('runtime.discord_channels.0.client_key', 'saved-' . $participant->operation_discord_channel_id)
                ->where('runtime.participants.0.operation_discord_channel_key', 'saved-' . $participant->operation_discord_channel_id)
            );
    }

    public function test_operation_channel_sync_creates_discord_channels_and_moves_assigned_members(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_category_id', '1454412961828438092');
        config()->set('services.discord.operation_member_role_id', '1454412917746438289');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-channels' => Http::response([
                'message' => 'Operation Discord channels synced.',
                'channels' => [
                    [
                        'operation_channel_id' => 1,
                        'discord_channel_id' => '1900',
                        'name' => 'Hammer Team',
                    ],
                ],
                'created_channel_count' => 1,
                'renamed_channel_count' => 0,
                'deleted_channel_count' => 0,
                'moved_member_count' => 1,
                'missing_guild_member_ids' => [],
            ]),
        ]);

        $creator = $this->verifiedUser();
        $activeUser = $this->verifiedUser(['discord_id' => '555']);

        $operation = $this->publishedOperation($creator);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $activeUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now(),
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/run/channels/sync', [
                'channels' => [
                    [
                        'client_key' => 'draft-1',
                        'name' => 'Hammer Team',
                    ],
                ],
                'participant_assignments' => [
                    [
                        'participant_id' => $participant->id,
                        'channel_key' => 'draft-1',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Discord channels synced. 1 created, 0 renamed, 0 deleted, 1 moved.');

        $this->assertDatabaseHas('operation_discord_channels', [
            'operation_id' => $operation->id,
            'name' => 'Hammer Team',
            'discord_channel_id' => '1900',
        ]);
    }

    public function test_operation_channel_sync_accepts_partial_bot_payload_and_persists_returned_channel_ids(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_category_id', '1454412961828438092');
        config()->set('services.discord.operation_member_role_id', '1454412917746438289');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-channels' => Http::response([
                'message' => 'Operation Discord channels synced.',
                'channels' => [
                    [
                        'operation_channel_id' => 1,
                        'discord_channel_id' => '2900',
                        'name' => 'Hammer Team',
                    ],
                ],
            ]),
        ]);

        $creator = $this->verifiedUser();
        $activeUser = $this->verifiedUser(['discord_id' => 'partial-layout-1']);

        $operation = $this->publishedOperation($creator);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $activeUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now(),
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/run/channels/sync', [
                'channels' => [
                    [
                        'client_key' => 'draft-1',
                        'name' => 'Hammer Team',
                    ],
                ],
                'participant_assignments' => [
                    [
                        'participant_id' => $participant->id,
                        'channel_key' => 'draft-1',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Discord channels synced. 0 created, 0 renamed, 0 deleted, 0 moved.');

        $this->assertDatabaseHas('operation_discord_channels', [
            'operation_id' => $operation->id,
            'name' => 'Hammer Team',
            'discord_channel_id' => '2900',
        ]);
    }

    public function test_operation_channel_sync_failure_does_not_mutate_saved_layout_or_assignments(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_category_id', '1454412961828438092');
        config()->set('services.discord.operation_member_role_id', '1454412917746438289');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-channels' => Http::response([
                'message' => 'Discord sync failed before applying channel changes.',
            ], 500),
        ]);

        $creator = $this->verifiedUser();
        $activeUser = $this->verifiedUser(['discord_id' => 'layout-fail-1']);

        $operation = $this->publishedOperation($creator);

        $channel = $operation->discordChannels()->create([
            'name' => 'Original Team',
            'discord_channel_id' => '1888',
            'sort_order' => 0,
        ]);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $activeUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now(),
            'operation_discord_channel_id' => $channel->id,
        ]);

        $this->actingAs($creator)
            ->from('/operations/' . $operation->id . '/run')
            ->post('/operations/' . $operation->id . '/run/channels/sync', [
                'channels' => [
                    [
                        'id' => $channel->id,
                        'client_key' => 'saved-' . $channel->id,
                        'name' => 'Renamed Team',
                    ],
                    [
                        'client_key' => 'draft-2',
                        'name' => 'New Team',
                    ],
                ],
                'participant_assignments' => [
                    [
                        'participant_id' => $participant->id,
                        'channel_key' => 'draft-2',
                    ],
                ],
            ])
            ->assertRedirect('/operations/' . $operation->id . '/run')
            ->assertSessionHasErrors('discord');

        $channel->refresh();
        $participant->refresh();

        $this->assertSame('Original Team', $channel->name);
        $this->assertSame('1888', $channel->discord_channel_id);
        $this->assertSame($channel->id, $participant->operation_discord_channel_id);
        $this->assertDatabaseMissing('operation_discord_channels', [
            'operation_id' => $operation->id,
            'name' => 'New Team',
        ]);
    }

    public function test_operation_channel_sync_can_delete_removed_discord_channels(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_category_id', '1454412961828438092');
        config()->set('services.discord.operation_member_role_id', '1454412917746438289');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-channels' => Http::response([
                'message' => 'Operation Discord channels synced.',
                'channels' => [],
                'created_channel_count' => 0,
                'renamed_channel_count' => 0,
                'deleted_channel_count' => 1,
                'moved_member_count' => 0,
                'missing_guild_member_ids' => [],
            ]),
        ]);

        $creator = $this->verifiedUser();
        $operation = $this->publishedOperation($creator);

        $channel = $operation->discordChannels()->create([
            'name' => 'Old Channel',
            'discord_channel_id' => '1999',
            'sort_order' => 0,
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/run/channels/sync', [
                'channels' => [],
                'participant_assignments' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Discord channels synced. 0 created, 0 renamed, 1 deleted, 0 moved.');

        $this->assertDatabaseMissing('operation_discord_channels', [
            'id' => $channel->id,
        ]);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'http://bot.test/bot/operations/runtime/sync-channels'
                && ($data['delete_channel_ids'] ?? []) === ['1999']
                && ($data['channels'] ?? []) === [];
        });
    }

    public function test_completing_an_operation_deletes_runtime_discord_channels(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_category_id', '1454412961828438092');
        config()->set('services.discord.operation_member_role_id', '1454412917746438289');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-channels' => Http::response([
                'message' => 'Operation Discord channels synced.',
                'channels' => [],
                'created_channel_count' => 0,
                'renamed_channel_count' => 0,
                'deleted_channel_count' => 1,
                'moved_member_count' => 0,
                'missing_guild_member_ids' => [],
            ]),
        ]);

        $creator = $this->verifiedUser();
        $activeUser = $this->verifiedUser(['discord_id' => '555']);

        $operation = $this->publishedOperation($creator, [
            'status' => 'in_progress',
        ]);

        $channel = $operation->discordChannels()->create([
            'name' => 'Hammer Team',
            'discord_channel_id' => '1999',
            'sort_order' => 0,
        ]);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $activeUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now(),
            'operation_discord_channel_id' => $channel->id,
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/complete', [
                'outcome' => 'success',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Operation completed.');

        $this->assertDatabaseMissing('operation_discord_channels', [
            'id' => $channel->id,
        ]);

        $participant->refresh();
        $this->assertNull($participant->operation_discord_channel_id);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'http://bot.test/bot/operations/runtime/sync-channels'
                && ($data['delete_channel_ids'] ?? []) === ['1999']
                && ($data['channels'] ?? []) === [];
        });
    }

    public function test_completing_an_operation_clears_local_runtime_layout_even_when_cleanup_payload_is_sparse(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_category_id', '1454412961828438092');
        config()->set('services.discord.operation_member_role_id', '1454412917746438289');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-channels' => Http::response([
                'message' => 'Operation Discord channels synced.',
            ]),
        ]);

        $creator = $this->verifiedUser();
        $activeUser = $this->verifiedUser(['discord_id' => '555-sparse-cleanup']);

        $operation = $this->publishedOperation($creator, [
            'status' => 'in_progress',
        ]);

        $channel = $operation->discordChannels()->create([
            'name' => 'Hammer Team',
            'discord_channel_id' => '1999',
            'sort_order' => 0,
        ]);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $activeUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now(),
            'operation_discord_channel_id' => $channel->id,
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/complete', [
                'outcome' => 'success',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Operation completed.');

        $this->assertDatabaseMissing('operation_discord_channels', [
            'id' => $channel->id,
        ]);

        $participant->refresh();
        $this->assertNull($participant->operation_discord_channel_id);
    }

    public function test_completing_an_operation_keeps_completion_but_surfaces_cleanup_warning_when_discord_cleanup_fails(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_category_id', '1454412961828438092');
        config()->set('services.discord.operation_member_role_id', '1454412917746438289');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-channels' => Http::response([
                'unexpected' => 'payload',
            ], 503),
        ]);

        $creator = $this->verifiedUser();
        $activeUser = $this->verifiedUser(['discord_id' => '555-cleanup-fail']);

        $operation = $this->publishedOperation($creator, [
            'status' => 'in_progress',
        ]);

        $channel = $operation->discordChannels()->create([
            'name' => 'Hammer Team',
            'discord_channel_id' => '1999',
            'sort_order' => 0,
        ]);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $activeUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now(),
            'operation_discord_channel_id' => $channel->id,
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/complete', [
                'outcome' => 'success',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Operation completed.')
            ->assertSessionHas(
                'warning',
                'Operation completed, but Discord channel cleanup needs attention: Discord bot rejected the request (503).'
            );

        $this->assertSame('completed', $operation->fresh()->status);
        $this->assertDatabaseHas('operation_discord_channels', [
            'id' => $channel->id,
        ]);

        $participant->refresh();
        $this->assertSame($channel->id, $participant->operation_discord_channel_id);
    }

    public function test_canceling_an_operation_deletes_runtime_discord_channels(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_category_id', '1454412961828438092');
        config()->set('services.discord.operation_member_role_id', '1454412917746438289');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-channels' => Http::response([
                'message' => 'Operation Discord channels synced.',
                'channels' => [],
                'created_channel_count' => 0,
                'renamed_channel_count' => 0,
                'deleted_channel_count' => 1,
                'moved_member_count' => 0,
                'missing_guild_member_ids' => [],
            ]),
        ]);

        $creator = $this->verifiedUser();
        $activeUser = $this->verifiedUser(['discord_id' => '555']);

        $operation = $this->publishedOperation($creator);

        $channel = $operation->discordChannels()->create([
            'name' => 'Hammer Team',
            'discord_channel_id' => '1999',
            'sort_order' => 0,
        ]);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $activeUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'operation_discord_channel_id' => $channel->id,
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/cancel', [
                'reason' => 'Weather scrub.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Operation canceled.');

        $this->assertDatabaseMissing('operation_discord_channels', [
            'id' => $channel->id,
        ]);

        $participant->refresh();
        $this->assertNull($participant->operation_discord_channel_id);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'http://bot.test/bot/operations/runtime/sync-channels'
                && ($data['delete_channel_ids'] ?? []) === ['1999']
                && ($data['channels'] ?? []) === [];
        });
    }

    public function test_canceling_an_operation_keeps_cancellation_but_surfaces_cleanup_warning_when_discord_cleanup_fails(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_category_id', '1454412961828438092');
        config()->set('services.discord.operation_member_role_id', '1454412917746438289');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-channels' => Http::response([
                'unexpected' => 'payload',
            ], 503),
        ]);

        $creator = $this->verifiedUser();
        $activeUser = $this->verifiedUser(['discord_id' => '555-cancel-cleanup-fail']);

        $operation = $this->publishedOperation($creator);

        $channel = $operation->discordChannels()->create([
            'name' => 'Hammer Team',
            'discord_channel_id' => '1999',
            'sort_order' => 0,
        ]);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $activeUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'operation_discord_channel_id' => $channel->id,
        ]);

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/cancel', [
                'reason' => 'Weather scrub.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Operation canceled.')
            ->assertSessionHas(
                'warning',
                'Operation canceled, but Discord channel cleanup needs attention: Discord bot rejected the request (503).'
            );

        $this->assertSame('canceled', $operation->fresh()->status);
        $this->assertDatabaseHas('operation_discord_channels', [
            'id' => $channel->id,
        ]);

        $participant->refresh();
        $this->assertSame($channel->id, $participant->operation_discord_channel_id);
    }

    public function test_starting_an_operation_returns_json_warning_when_discord_sync_fails(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_lobby_1_channel_id', '1454413000650788949');
        config()->set('services.discord.operation_lobby_2_channel_id', '1454413003142332448');
        config()->set('services.discord.operation_category_id', '1454412961828438092');
        config()->set('services.discord.operation_member_role_id', '1454412917746438289');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-channels' => Http::response([
                'unexpected' => 'payload',
            ], 503),
        ]);

        $creator = $this->verifiedUser();
        $operation = $this->publishedOperation($creator);

        $operation->discordChannels()->create([
            'name' => 'Hammer Team',
            'discord_channel_id' => '1999',
            'sort_order' => 0,
        ]);

        $this->actingAs($creator)
            ->postJson('/operations/' . $operation->id . '/start')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('payload.operation.id', $operation->id)
            ->assertJsonPath('payload.operation.status', 'in_progress')
            ->assertJsonPath('warning', 'Discord bot rejected the request (503).');
    }

    public function test_publishing_an_operation_returns_json_success_payload_without_warning(): void
    {
        $creator = $this->verifiedUser();
        $operation = $this->publishedOperation($creator, [
            'status' => 'draft',
        ]);

        $response = $this->actingAs($creator)
            ->postJson('/operations/' . $operation->id . '/publish');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('payload.operation.id', $operation->id)
            ->assertJsonPath('payload.operation.status', 'published');

        $this->assertArrayNotHasKey('warning', $response->json());
    }

    public function test_starting_an_operation_returns_json_success_payload_without_warning_when_channel_sync_is_not_needed(): void
    {
        $creator = $this->verifiedUser();
        $operation = $this->publishedOperation($creator);

        $response = $this->actingAs($creator)
            ->postJson('/operations/' . $operation->id . '/start');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('payload.operation.id', $operation->id)
            ->assertJsonPath('payload.operation.status', 'in_progress');

        $this->assertArrayNotHasKey('warning', $response->json());
    }

    public function test_completing_an_operation_returns_json_warning_when_discord_cleanup_fails(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_category_id', '1454412961828438092');
        config()->set('services.discord.operation_member_role_id', '1454412917746438289');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-channels' => Http::response([
                'unexpected' => 'payload',
            ], 503),
        ]);

        $creator = $this->verifiedUser();
        $operation = $this->publishedOperation($creator, [
            'status' => 'in_progress',
        ]);

        $operation->discordChannels()->create([
            'name' => 'Hammer Team',
            'discord_channel_id' => '1999',
            'sort_order' => 0,
        ]);

        $this->actingAs($creator)
            ->postJson('/operations/' . $operation->id . '/complete', [
                'outcome' => 'success',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('payload.operation.id', $operation->id)
            ->assertJsonPath('payload.operation.status', 'completed')
            ->assertJsonPath('warning', 'Discord bot rejected the request (503).');
    }

    public function test_canceling_an_operation_returns_json_warning_when_discord_cleanup_fails(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'test-secret');
        config()->set('services.discord.operation_category_id', '1454412961828438092');
        config()->set('services.discord.operation_member_role_id', '1454412917746438289');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-channels' => Http::response([
                'unexpected' => 'payload',
            ], 503),
        ]);

        $creator = $this->verifiedUser();
        $operation = $this->publishedOperation($creator);

        $operation->discordChannels()->create([
            'name' => 'Hammer Team',
            'discord_channel_id' => '1999',
            'sort_order' => 0,
        ]);

        $this->actingAs($creator)
            ->postJson('/operations/' . $operation->id . '/cancel', [
                'reason' => 'Weather scrub.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('payload.operation.id', $operation->id)
            ->assertJsonPath('payload.operation.status', 'canceled')
            ->assertJsonPath('warning', 'Discord bot rejected the request (503).');
    }

    public function test_complete_transition_json_validation_errors_use_standard_error_shape(): void
    {
        $creator = $this->verifiedUser();
        $operation = $this->publishedOperation($creator, [
            'status' => 'in_progress',
        ]);

        $this->actingAs($creator)
            ->postJson('/operations/' . $operation->id . '/complete', [])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('errors.outcome.0', 'The outcome field is required.');
    }

    public function test_cancel_transition_json_validation_errors_use_standard_error_shape(): void
    {
        $creator = $this->verifiedUser();
        $operation = $this->publishedOperation($creator);

        $this->actingAs($creator)
            ->postJson('/operations/' . $operation->id . '/cancel', [
                'reason' => '',
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('errors.reason.0', 'The reason field is required.');
    }

    public function test_start_transition_json_invalid_transition_uses_standard_error_shape(): void
    {
        $creator = $this->verifiedUser();
        $operation = $this->publishedOperation($creator, [
            'status' => 'draft',
        ]);

        $this->actingAs($creator)
            ->postJson('/operations/' . $operation->id . '/start')
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('errors.status.0', 'Invalid transition from draft to in_progress');
    }

    public function test_guest_json_transition_request_returns_unauthenticated_error(): void
    {
        $creator = $this->verifiedUser();
        $operation = $this->publishedOperation($creator);

        $this->postJson('/operations/' . $operation->id . '/start')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_non_manager_cannot_use_transition_routes_over_json(): void
    {
        $creator = $this->verifiedUser();
        $viewer = $this->verifiedUser(['discord_id' => 'forbidden-viewer']);

        $draftOperation = $this->publishedOperation($creator, [
            'status' => 'draft',
            'title' => 'Draft Transition Forbidden',
        ]);
        $publishedOperation = $this->publishedOperation($creator, [
            'status' => 'published',
            'title' => 'Published Transition Forbidden',
        ]);
        $inProgressOperation = $this->publishedOperation($creator, [
            'status' => 'in_progress',
            'title' => 'In Progress Transition Forbidden',
        ]);

        $this->actingAs($viewer)
            ->postJson('/operations/' . $draftOperation->id . '/publish')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->postJson('/operations/' . $publishedOperation->id . '/start')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->postJson('/operations/' . $inProgressOperation->id . '/complete', [
                'outcome' => 'success',
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->postJson('/operations/' . $publishedOperation->id . '/cancel', [
                'reason' => 'Not your mission.',
            ])
            ->assertForbidden();
    }

    public function test_non_manager_cannot_use_runtime_mutation_routes(): void
    {
        $creator = $this->verifiedUser();
        $viewer = $this->verifiedUser(['discord_id' => 'runtime-forbidden-viewer']);
        $participantUser = $this->verifiedUser(['discord_id' => 'runtime-forbidden-member']);
        $walkInUser = $this->verifiedUser(['discord_id' => 'runtime-walk-in-target']);

        $operation = $this->publishedOperation($creator, [
            'status' => 'published',
            'rsvp_deadline' => now()->subMinutes(15),
        ]);

        $participant = OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $participantUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        $this->actingAs($viewer)
            ->post('/operations/' . $operation->id . '/run/sync')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->put('/operations/' . $operation->id . '/run/funds-prep', [
                'money_rows' => [],
                'loot_rows' => [],
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post('/operations/' . $operation->id . '/run/participants/' . $participant->id, [
                'status' => 'present',
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post('/operations/' . $operation->id . '/run/walk-ins', [
                'user_id' => $walkInUser->id,
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post('/operations/' . $operation->id . '/run/layout', [
                'channels' => [],
                'participant_assignments' => [],
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post('/operations/' . $operation->id . '/run/channels/sync', [
                'channels' => [],
                'participant_assignments' => [],
            ])
            ->assertForbidden();
    }

    private function verifiedUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
        ], $attributes));
    }

    private function publishedOperation(User $creator, array $attributes = []): Operation
    {
        $operation = new Operation();
        $operation->forceFill(array_merge([
            'created_by' => $creator->id,
            'title' => 'Operation Runtime',
            'description' => 'Runtime tooling test operation.',
            'visibility' => 'open',
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(4),
            'rsvp_deadline' => now()->addHour(),
            'status' => 'published',
        ], $attributes));
        $operation->save();

        return $operation->fresh();
    }
}
