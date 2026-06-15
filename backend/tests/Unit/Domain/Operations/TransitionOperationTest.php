<?php

namespace Tests\Unit\Domain\Operations;

use App\Domain\Operations\Actions\TransitionOperation;
use App\Domain\Operations\Enums\CompletionOutcome;
use App\Domain\Operations\Enums\OperationStatus;
use App\Domain\Operations\Services\OperationService;
use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\OperationSettlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransitionOperationTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_transition_updates_completion_counters_via_domain_events(): void
    {
        $creator = User::factory()->create();
        $participant = User::factory()->create();

        $operation = Operation::create([
            'created_by' => $creator->id,
            'title' => 'Hammerfall',
            'starts_at' => now()->addDay(),
            'status' => OperationStatus::InProgress->value,
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $participant->id,
        ]);

        $updated = app(OperationService::class)->transition(
            $operation,
            OperationStatus::Completed->value,
            null,
            CompletionOutcome::Success->value
        );

        $this->assertSame(OperationStatus::Completed->value, $updated->status);
        $this->assertSame(CompletionOutcome::Success->value, $updated->completion_outcome);
        $this->assertDatabaseHas('users', [
            'id' => $participant->id,
            'operations_completed_count' => 1,
            'operations_no_show_count' => 0,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $creator->id,
            'operations_success_count' => 1,
        ]);
        $this->assertNotNull($updated->after_action_report);
        $this->assertSame([$participant->id], $updated->after_action_attendance_user_ids);
        $this->assertSame([], $updated->after_action_no_show_user_ids);
        $this->assertNotNull($updated->after_action_report_updated_at);
    }

    public function test_completed_transition_uses_runtime_sync_defaults_for_attendance_no_show_signed_off_and_excused(): void
    {
        $creator = User::factory()->create();
        $presentParticipant = User::factory()->create();
        $noShowParticipant = User::factory()->create();
        $signedOffParticipant = User::factory()->create();
        $techIssueParticipant = User::factory()->create();
        $excusedParticipant = User::factory()->create();

        $operation = Operation::create([
            'created_by' => $creator->id,
            'title' => 'Runtime Defaults',
            'starts_at' => now()->addDay(),
            'status' => OperationStatus::InProgress->value,
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $presentParticipant->id,
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now(),
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $noShowParticipant->id,
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_NO_SHOW,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $signedOffParticipant->id,
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_OFF_BEFORE_START,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'signed_off_at' => now(),
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $techIssueParticipant->id,
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_TECHNICAL_ISSUE,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_MANUAL,
            'synced_in_at' => now(),
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $excusedParticipant->id,
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_EXCUSED,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_MANUAL,
        ]);

        $updated = app(OperationService::class)->transition(
            $operation,
            OperationStatus::Completed->value,
            null,
            CompletionOutcome::Success->value
        );

        $this->assertSame([$presentParticipant->id], $updated->after_action_attendance_user_ids);
        $this->assertSame([$noShowParticipant->id], $updated->after_action_no_show_user_ids);
        $this->assertSame([$signedOffParticipant->id], $updated->after_action_signed_off_early_user_ids);
        $this->assertSame([$techIssueParticipant->id, $excusedParticipant->id], $updated->after_action_excused_user_ids);
    }

    public function test_completed_transition_seeds_settlement_money_rows_from_funds_prep(): void
    {
        $creator = User::factory()->create();
        $presentParticipant = User::factory()->create(['rsi_handle' => 'PresentPilot']);
        $noShowParticipant = User::factory()->create(['rsi_handle' => 'NoShowPilot']);

        $operation = Operation::create([
            'created_by' => $creator->id,
            'title' => 'Seeded Settlement',
            'starts_at' => now()->addDay(),
            'status' => OperationStatus::InProgress->value,
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $presentParticipant->id,
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now(),
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $noShowParticipant->id,
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_NO_SHOW,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        OperationSettlement::create([
            'operation_id' => $operation->id,
            'prep_money_rows' => [
                [
                    'row_key' => 'prep-valid',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $presentParticipant->id,
                    'amount' => 75000,
                    'notes' => 'Valid prep row',
                ],
                [
                    'row_key' => 'prep-invalid',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $noShowParticipant->id,
                    'amount' => 42000,
                    'notes' => 'Needs reassignment',
                ],
            ],
        ]);

        $updated = app(OperationService::class)->transition(
            $operation,
            OperationStatus::Completed->value,
            null,
            CompletionOutcome::Success->value
        );

        $settlement = $updated->settlement()->firstOrFail();

        $this->assertSame('member', $settlement->money_rows[0]['recipient_type']);
        $this->assertSame($presentParticipant->id, $settlement->money_rows[0]['recipient_user_id']);
        $this->assertSame(75000, $settlement->money_rows[0]['amount']);
        $this->assertNull($settlement->money_rows[1]['recipient_type']);
        $this->assertNull($settlement->money_rows[1]['recipient_user_id']);
        $this->assertSame(42000, $settlement->money_rows[1]['amount']);
        $this->assertStringContainsString('Prep target requires review: NoShowPilot', $settlement->money_rows[1]['notes']);
    }

    public function test_canceled_transition_updates_creator_canceled_counter(): void
    {
        $creator = User::factory()->create();

        $operation = Operation::create([
            'created_by' => $creator->id,
            'title' => 'Silent Orbit',
            'starts_at' => now()->addDay(),
            'status' => OperationStatus::Published->value,
        ]);

        $updated = (new TransitionOperation())->execute(
            $operation,
            OperationStatus::Canceled->value,
            'Weather window closed.'
        );

        $this->assertSame(OperationStatus::Canceled->value, $updated->status);
        $this->assertSame('Weather window closed.', $updated->cancellation_reason);
        $this->assertDatabaseHas('users', [
            'id' => $creator->id,
            'operations_canceled_count' => 1,
        ]);
    }

    public function test_canceled_transition_requires_reason(): void
    {
        $creator = User::factory()->create();

        $operation = Operation::create([
            'created_by' => $creator->id,
            'title' => 'Darkwater',
            'starts_at' => now()->addDay(),
            'status' => OperationStatus::Published->value,
        ]);

        $this->expectException(ValidationException::class);

        (new TransitionOperation())->execute(
            $operation,
            OperationStatus::Canceled->value,
            '   '
        );
    }

    public function test_invalid_transition_throws_validation_exception(): void
    {
        $creator = User::factory()->create();

        $operation = Operation::create([
            'created_by' => $creator->id,
            'title' => 'Broken Chain',
            'starts_at' => now()->addDay(),
            'status' => OperationStatus::Draft->value,
        ]);

        $this->expectException(ValidationException::class);

        (new TransitionOperation())->execute(
            $operation,
            OperationStatus::Completed->value,
            null,
            CompletionOutcome::Failed->value
        );
    }
}
