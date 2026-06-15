<?php

namespace Tests\Unit\Domain\Operations;

use App\Domain\Operations\Enums\OperationStatus;
use App\Domain\Operations\Services\ParticipantService;
use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\OperationRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ParticipantServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_join_rejects_operations_past_the_rsvp_deadline(): void
    {
        $service = app(ParticipantService::class);
        $operation = $this->publishedOperation([
            'rsvp_deadline' => now()->subMinute(),
            'starts_at' => now()->addHour(),
        ]);

        $exception = $this->captureValidationException(fn () => $service->join($operation, User::factory()->create(), []));

        $this->assertSame(
            'The sign-up deadline for this operation has passed.',
            $exception->errors()['participant'][0] ?? null,
        );
    }

    public function test_join_rejects_slots_not_defined_on_the_operation(): void
    {
        $service = app(ParticipantService::class);
        $operation = $this->publishedOperation([
            'slots' => ['Pilot', 'Medic'],
        ]);

        $exception = $this->captureValidationException(fn () => $service->join($operation, User::factory()->create(), [
            'slot' => 'Gunner',
        ]));

        $this->assertSame(
            'Selected slot is not available for this operation.',
            $exception->errors()['slot'][0] ?? null,
        );
    }

    public function test_join_rejects_roles_that_belong_to_another_operation(): void
    {
        $service = app(ParticipantService::class);
        $operation = $this->publishedOperation();
        $foreignOperation = $this->publishedOperation(['title' => 'Foreign op']);
        $foreignRole = OperationRole::query()->create([
            'operation_id' => $foreignOperation->id,
            'role_name' => 'gunner',
            'role_display_name' => 'Gunner',
            'capacity' => 1,
        ]);

        $exception = $this->captureValidationException(fn () => $service->join($operation, User::factory()->create(), [
            'operation_role_id' => $foreignRole->id,
        ]));

        $this->assertSame(
            'Invalid role for this operation',
            $exception->errors()['operation_role_id'][0] ?? null,
        );
    }

    public function test_join_rejects_roles_that_are_already_full(): void
    {
        $service = app(ParticipantService::class);
        $operation = $this->publishedOperation();
        $role = OperationRole::query()->create([
            'operation_id' => $operation->id,
            'role_name' => 'escort',
            'role_display_name' => 'Escort',
            'capacity' => 1,
        ]);

        OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => User::factory()->create()->id,
            'operation_role_id' => $role->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        $exception = $this->captureValidationException(fn () => $service->join($operation, User::factory()->create(), [
            'operation_role_id' => $role->id,
        ]));

        $this->assertSame('Role is full', $exception->errors()['operation_role_id'][0] ?? null);
    }

    public function test_join_can_reactivate_a_signed_off_participant_row(): void
    {
        $service = app(ParticipantService::class);
        $user = User::factory()->create(['operations_joined_count' => 0]);
        $operation = $this->publishedOperation([
            'slots' => ['Pilot'],
        ]);
        $role = OperationRole::query()->create([
            'operation_id' => $operation->id,
            'role_name' => 'pilot',
            'role_display_name' => 'Pilot',
            'capacity' => 2,
        ]);

        $participant = OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $user->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_OFF_BEFORE_START,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'signed_off_at' => now()->subMinutes(10),
        ]);

        $reactivated = $service->join($operation, $user, [
            'slot' => 'Pilot',
            'operation_role_id' => $role->id,
            'notes' => 'Rejoining after signing off',
        ]);

        $this->assertSame($participant->id, $reactivated->id);
        $this->assertSame('Pilot', $reactivated->slot);
        $this->assertSame($role->id, $reactivated->operation_role_id);
        $this->assertSame(OperationParticipant::RUNTIME_STATUS_SIGNED_UP, $reactivated->runtime_status);
        $this->assertNull($reactivated->signed_off_at);
        $this->assertSame(1, $user->fresh()->operations_joined_count);
    }

    public function test_leave_rejects_operations_that_have_already_started(): void
    {
        $service = app(ParticipantService::class);
        $user = User::factory()->create();
        $operation = $this->publishedOperation([
            'starts_at' => now()->subMinute(),
            'rsvp_deadline' => now()->subHour(),
        ]);

        OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $user->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        $exception = $this->captureValidationException(fn () => $service->leave($operation, $user));

        $this->assertSame(
            'This operation has already started. Leaving is locked.',
            $exception->errors()['participant'][0] ?? null,
        );
    }

    private function publishedOperation(array $attributes = []): Operation
    {
        $operation = new Operation();
        $operation->forceFill(array_merge([
            'created_by' => User::factory()->create()->id,
            'title' => 'Participant service test op',
            'description' => 'Operation for participant service tests.',
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
