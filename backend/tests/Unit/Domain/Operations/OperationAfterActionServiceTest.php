<?php

namespace Tests\Unit\Domain\Operations;

use App\Domain\Operations\Enums\CompletionOutcome;
use App\Domain\Operations\Enums\OperationStatus;
use App\Domain\Operations\Services\OperationAfterActionService;
use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\OperationSettlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationAfterActionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_defaults_builds_attendance_buckets_from_runtime_statuses(): void
    {
        $service = app(OperationAfterActionService::class);
        $operation = $this->completedOperation([
            'completion_outcome' => CompletionOutcome::Success->value,
            'after_action_report' => null,
            'after_action_attendance_user_ids' => [],
            'after_action_no_show_user_ids' => [],
            'after_action_signed_off_early_user_ids' => [],
            'after_action_excused_user_ids' => [],
        ]);

        $attendedUser = $this->member();
        $noShowUser = $this->member();
        $signedOffUser = $this->member();
        $technicalIssueUser = $this->member();

        OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $attendedUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now()->subMinutes(5),
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
            'user_id' => $signedOffUser->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_OFF_BEFORE_START,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'signed_off_at' => now()->subMinutes(15),
        ]);

        OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $technicalIssueUser->id,
            'attendance_status' => 'missed',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_TECHNICAL_ISSUE,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_MANUAL,
            'synced_in_at' => now()->subMinutes(10),
        ]);

        $updated = $service->ensureDefaults($operation, OperationStatus::Completed->value);

        $this->assertStringContainsString('Outcome: Success', (string) $updated->after_action_report);
        $this->assertSame([$attendedUser->id], $updated->after_action_attendance_user_ids);
        $this->assertSame([$noShowUser->id], $updated->after_action_no_show_user_ids);
        $this->assertSame([$signedOffUser->id], $updated->after_action_signed_off_early_user_ids);
        $this->assertSame([$technicalIssueUser->id], $updated->after_action_excused_user_ids);
        $this->assertNotNull($updated->after_action_report_updated_at);

        $this->assertSame(1, $attendedUser->fresh()->operations_completed_count);
        $this->assertSame(1, $noShowUser->fresh()->operations_no_show_count);
        $this->assertSame(1, $technicalIssueUser->fresh()->operations_excused_count);
    }

    public function test_ensure_defaults_without_runtime_signals_falls_back_to_all_participants_as_attended(): void
    {
        $service = app(OperationAfterActionService::class);
        $operation = $this->completedOperation([
            'after_action_report' => null,
            'after_action_attendance_user_ids' => [],
            'after_action_no_show_user_ids' => [],
            'after_action_signed_off_early_user_ids' => [],
            'after_action_excused_user_ids' => [],
        ]);

        $firstUser = $this->member();
        $secondUser = $this->member();

        OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $firstUser->id,
        ]);

        OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $secondUser->id,
        ]);

        $updated = $service->ensureDefaults($operation, OperationStatus::Completed->value);

        $this->assertSame([$firstUser->id, $secondUser->id], $updated->after_action_attendance_user_ids);
        $this->assertSame([], $updated->after_action_no_show_user_ids);
        $this->assertSame([], $updated->after_action_signed_off_early_user_ids);
        $this->assertSame([], $updated->after_action_excused_user_ids);
    }

    public function test_update_report_blocks_attendance_changes_after_settlement_finalization(): void
    {
        $service = app(OperationAfterActionService::class);
        $member = $this->member();
        $operation = $this->completedOperation([
            'after_action_report' => 'Locked report',
            'after_action_attendance_user_ids' => [$member->id],
            'after_action_no_show_user_ids' => [],
            'after_action_signed_off_early_user_ids' => [],
            'after_action_excused_user_ids' => [],
        ]);

        OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
            'finalized_by_user_id' => $this->member()->id,
            'finalized_at' => now(),
        ]);

        $exception = $this->captureValidationException(fn () => $service->updateReport(
            $operation,
            'Locked report',
            [],
            [$member->id],
            [],
            []
        ));

        $this->assertSame(
            'Final attendance is locked once the operation settlement is finalized. Reopen the settlement to change attendance, no-shows, signed-off-early members, or excused members.',
            $exception->errors()['attendance_user_ids'][0] ?? null,
        );
    }

    public function test_update_report_removes_overlaps_from_later_buckets(): void
    {
        $service = app(OperationAfterActionService::class);
        $attendedUser = $this->member();
        $sharedUser = $this->member();
        $excusedUser = $this->member();
        $operation = $this->completedOperation();

        $updated = $service->updateReport(
            $operation,
            'Updated report',
            [$attendedUser->id, $sharedUser->id],
            [$sharedUser->id, $excusedUser->id],
            [$sharedUser->id],
            [$sharedUser->id, $excusedUser->id]
        );

        $this->assertSame([$attendedUser->id, $sharedUser->id], $updated->after_action_attendance_user_ids);
        $this->assertSame([$excusedUser->id], $updated->after_action_no_show_user_ids);
        $this->assertSame([], $updated->after_action_signed_off_early_user_ids);
        $this->assertSame([], $updated->after_action_excused_user_ids);
    }

    private function completedOperation(array $attributes = []): Operation
    {
        $operation = new Operation();
        $operation->forceFill(array_merge([
            'created_by' => $this->member()->id,
            'title' => 'AAR Service Test',
            'description' => 'Completed operation for AAR service tests.',
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
            'status' => OperationStatus::Completed->value,
            'completion_outcome' => CompletionOutcome::Success->value,
            'after_action_attendance_user_ids' => [],
            'after_action_no_show_user_ids' => [],
            'after_action_signed_off_early_user_ids' => [],
            'after_action_excused_user_ids' => [],
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

    private function captureValidationException(callable $callback): ValidationException
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            return $exception;
        }

        $this->fail('Expected a validation exception.');
    }
}
