<?php

namespace Tests\Unit\Domain\Operations;

use App\Domain\Operations\Actions\TransitionOperation;
use App\Domain\Operations\Enums\CompletionOutcome;
use App\Domain\Operations\Enums\OperationStatus;
use App\Domain\Operations\Services\OperationService;
use App\Models\Operation;
use App\Models\OperationParticipant;
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
