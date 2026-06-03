<?php

namespace Tests\Feature;

use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationAfterActionReportWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_can_update_completed_operation_after_action_report_attendance_and_no_show_stats(): void
    {
        $creator = $this->verifiedUser([
            'rank' => 'cit',
            'rank_level' => 3,
        ]);

        $signedUpUser = $this->verifiedUser();
        $noShowUser = $this->verifiedUser();
        $walkInUser = $this->verifiedUser();

        $operation = $this->completedOperation($creator, [
            'after_action_report' => 'Initial report',
            'after_action_attendance_user_ids' => [$signedUpUser->id],
            'after_action_no_show_user_ids' => [],
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $signedUpUser->id,
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $noShowUser->id,
        ]);

        $response = $this
            ->actingAs($creator)
            ->from('/operations/dashboard?operation=' . $operation->id)
            ->put('/operations/' . $operation->id . '/after-action-report', [
                'after_action_report' => 'Updated AAR summary',
                'attendance_user_ids' => [$signedUpUser->id, $walkInUser->id],
                'no_show_user_ids' => [$noShowUser->id],
            ]);

        $response
            ->assertRedirect('/operations/dashboard?operation=' . $operation->id)
            ->assertSessionHas('success', 'After Action Report updated.');

        $operation->refresh();

        $this->assertSame('Updated AAR summary', $operation->after_action_report);
        $this->assertSame([$signedUpUser->id, $walkInUser->id], $operation->after_action_attendance_user_ids);
        $this->assertSame([$noShowUser->id], $operation->after_action_no_show_user_ids);
        $this->assertNotNull($operation->after_action_report_updated_at);

        $this->assertDatabaseHas('users', [
            'id' => $signedUpUser->id,
            'operations_completed_count' => 1,
            'operations_no_show_count' => 0,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $walkInUser->id,
            'operations_completed_count' => 1,
            'operations_no_show_count' => 0,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $noShowUser->id,
            'operations_completed_count' => 0,
            'operations_no_show_count' => 1,
        ]);

        $this
            ->actingAs($creator)
            ->get('/user/' . $noShowUser->id)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/userpage')
                ->where('profileUser.operations_no_show_count', null)
            );
    }

    public function test_non_creator_non_director_cannot_update_completed_operation_after_action_report(): void
    {
        $creator = $this->verifiedUser([
            'rank' => 'cit',
            'rank_level' => 3,
        ]);

        $otherOfficer = $this->verifiedUser([
            'rank' => 'lieutenant',
            'rank_level' => 2,
        ]);

        $operation = $this->completedOperation($creator);

        $this
            ->actingAs($otherOfficer)
            ->put('/operations/' . $operation->id . '/after-action-report', [
                'after_action_report' => 'Unauthorized update',
                'attendance_user_ids' => [],
            ])
            ->assertForbidden();
    }

    public function test_admin_dashboard_includes_completed_operations_for_aar_management(): void
    {
        $director = $this->directorUser();
        $creator = $this->verifiedUser();
        $participant = $this->verifiedUser();

        $operation = $this->completedOperation($creator, [
            'title' => 'Pyro Debrief',
            'after_action_report' => 'Admin-visible report',
            'after_action_attendance_user_ids' => [$participant->id],
            'after_action_no_show_user_ids' => [$creator->id],
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $participant->id,
        ]);

        $this
            ->actingAs($director)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('operations.0.id', $operation->id)
                ->where('operations.0.after_action_report', 'Admin-visible report')
                ->where('operations.0.after_action_attendance.0.id', $participant->id)
                ->where('operations.0.after_action_no_show.0.id', $creator->id)
            );
    }

    public function test_operations_dashboard_after_action_payload_includes_no_show_user_data_for_signed_up_members(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser();

        $operation = $this->completedOperation($creator, [
            'title' => 'No Show Roster Sync',
            'after_action_report' => 'Signed-up no-show roster test',
            'after_action_attendance_user_ids' => [],
            'after_action_no_show_user_ids' => [$participant->id],
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $participant->id,
            'slot' => 'Escort',
        ]);

        $response = $this
            ->actingAs($creator)
            ->get('/operations/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Operations/OperationDashboard')
            ->where('afterActionOperations.0.id', $operation->id)
            ->where('afterActionOperations.0.after_action_no_show.0.id', $participant->id)
        );
    }

    public function test_active_operation_payload_includes_verified_members_for_aar_attendance_adjustments(): void
    {
        $creator = $this->directorUser();

        $participant = $this->verifiedUser();
        $walkInUser = $this->verifiedUser();

        $operation = $this->completedOperation($creator, [
            'after_action_report' => 'Roster report',
            'after_action_attendance_user_ids' => [$participant->id, $walkInUser->id],
            'after_action_no_show_user_ids' => [$creator->id],
        ]);

        OperationParticipant::create([
            'operation_id' => $operation->id,
            'user_id' => $participant->id,
            'slot' => 'Pilot',
        ]);

        $this
            ->actingAs($creator)
            ->get('/operations/dashboard?operation=' . $operation->id)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/OperationDashboard')
                ->where('activeOperation.operation.after_action_report', 'Roster report')
                ->where('activeOperation.operation.after_action_attendance.0.id', $participant->id)
                ->where('activeOperation.operation.after_action_no_show.0.id', $creator->id)
                ->has('activeOperation.verifiedMembers', 3)
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

    private function completedOperation(User $creator, array $attributes = []): Operation
    {
        $operation = new Operation();
        $operation->forceFill(array_merge([
            'created_by' => $creator->id,
            'title' => 'Post Op Review',
            'description' => 'Completed operation.',
            'starts_at' => now()->subHour(),
            'ends_at' => now(),
            'status' => 'completed',
            'completion_outcome' => 'success',
            'after_action_attendance_user_ids' => [],
            'after_action_no_show_user_ids' => [],
        ], $attributes));
        $operation->save();

        return $operation->fresh();
    }
}
