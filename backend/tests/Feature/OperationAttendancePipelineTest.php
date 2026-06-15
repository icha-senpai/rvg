<?php

namespace Tests\Feature;

use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationAttendancePipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_statuses_flow_through_completion_settlement_rules_and_profile_stats(): void
    {
        $creator = $this->directorUser();
        $attendedUser = $this->verifiedUser(['rsi_handle' => 'AttendedPilot']);
        $noShowUser = $this->verifiedUser(['rsi_handle' => 'NoShowPilot']);
        $signedOffUser = $this->verifiedUser(['rsi_handle' => 'SignedOffPilot']);
        $excusedUser = $this->verifiedUser(['rsi_handle' => 'ExcusedPilot']);

        $operation = $this->publishedOperation($creator, [
            'rsvp_deadline' => now()->subMinutes(5),
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
        ]);

        $attendedParticipant = $this->signedUpParticipant($operation, $attendedUser);
        $noShowParticipant = $this->signedUpParticipant($operation, $noShowUser);
        $this->signedUpParticipant($operation, $signedOffUser);
        $excusedParticipant = $this->signedUpParticipant($operation, $excusedUser);

        $this->actingAs($signedOffUser)
            ->from('/operations/' . $operation->id)
            ->post('/operations/' . $operation->id . '/leave')
            ->assertRedirect('/operations/' . $operation->id)
            ->assertSessionHas('success', 'Signed off before start.');

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/run/participants/' . $attendedParticipant->id, [
                'status' => 'operation_finished',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Runtime status updated.');

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/run/participants/' . $noShowParticipant->id, [
                'status' => 'no_show',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Runtime status updated.');

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/run/participants/' . $excusedParticipant->id, [
                'status' => 'excused',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Runtime status updated.');

        $operation->forceFill([
            'status' => 'in_progress',
            'starts_at' => now()->subHour(),
            'ends_at' => now(),
        ])->save();

        $this->actingAs($creator)
            ->post('/operations/' . $operation->id . '/complete', [
                'outcome' => 'success',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Operation completed.');

        $operation->refresh();

        $this->assertSame([$attendedUser->id], $operation->after_action_attendance_user_ids);
        $this->assertSame([$noShowUser->id], $operation->after_action_no_show_user_ids);
        $this->assertSame([$signedOffUser->id], $operation->after_action_signed_off_early_user_ids);
        $this->assertSame([$excusedUser->id], $operation->after_action_excused_user_ids);

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.finalize', $operation), [
                'money_rows' => [
                    [
                        'row_key' => 'bad-pay',
                        'recipient_type' => 'member',
                        'recipient_user_id' => $signedOffUser->id,
                        'amount' => 1000,
                    ],
                ],
                'loot_rows' => [],
            ])
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHasErrors('recipient_user_id');

        $this->actingAs($creator)
            ->get('/operations/dashboard?operation=' . $operation->id)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/OperationDashboard')
                ->where('activeOperation.operation.after_action_attendance.0.id', $attendedUser->id)
                ->where('activeOperation.operation.after_action_no_show.0.id', $noShowUser->id)
                ->where('activeOperation.operation.after_action_signed_off_early.0.id', $signedOffUser->id)
                ->where('activeOperation.operation.after_action_excused.0.id', $excusedUser->id)
                ->has('activeOperation.operation.operation_settlement.eligible_recipients', 2)
                ->where('activeOperation.operation.operation_settlement.eligible_recipients.0.recipient_type', 'organization')
                ->where('activeOperation.operation.operation_settlement.eligible_recipients.1.recipient_user_id', $attendedUser->id)
            );

        $this->assertDatabaseHas('users', [
            'id' => $attendedUser->id,
            'operations_completed_count' => 1,
            'operations_no_show_count' => 0,
            'operations_excused_count' => 0,
            'operations_left_early_count' => 0,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $noShowUser->id,
            'operations_completed_count' => 0,
            'operations_no_show_count' => 1,
            'operations_excused_count' => 0,
            'operations_left_early_count' => 0,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $excusedUser->id,
            'operations_completed_count' => 0,
            'operations_no_show_count' => 0,
            'operations_excused_count' => 1,
            'operations_left_early_count' => 0,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $signedOffUser->id,
            'operations_completed_count' => 0,
            'operations_no_show_count' => 0,
            'operations_excused_count' => 0,
            'operations_left_early_count' => 1,
        ]);

        $this->actingAs($creator)
            ->get(route('member.profile', $signedOffUser->rsi_handle))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/userpage')
                ->where('profileUser.operations_left_early_count', 1)
                ->where('profileUser.operations_no_show_count', 0)
                ->where('profileUser.operations_excused_count', 0)
                ->where('profileUser.operations_completed_count', 0)
            );
    }

    private function verifiedUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
        ], $attributes));
    }

    private function directorUser(array $attributes = []): User
    {
        $directorRole = Role::create([
            'name' => 'Director',
            'slug' => 'director',
            'description' => 'Test director role',
            'is_system' => true,
        ]);

        $user = $this->verifiedUser(array_merge([
            'rank' => 'admiral',
            'rank_level' => 5,
        ], $attributes));

        $user->roles()->attach($directorRole->id);
        $user->load('roles');

        return $user;
    }

    private function publishedOperation(User $creator, array $attributes = []): Operation
    {
        $operation = new Operation();
        $operation->forceFill(array_merge([
            'created_by' => $creator->id,
            'title' => 'Attendance Pipeline Operation',
            'description' => 'Attendance pipeline test operation.',
            'visibility' => 'open',
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(4),
            'rsvp_deadline' => now()->addHour(),
            'status' => 'published',
        ], $attributes));
        $operation->save();

        return $operation->fresh();
    }

    private function signedUpParticipant(Operation $operation, User $user): OperationParticipant
    {
        return OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $user->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);
    }
}
