<?php

namespace Tests\Feature;

use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\Role;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationWebInertiaFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_query_props_include_active_and_editing_operation_payloads(): void
    {
        $user = $this->actingAsDirector();

        $operation = $this->makeOperation($user, [
            'status' => 'published',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/operations/dashboard?operation=' . $operation->id . '&edit=' . $operation->id);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Operations/OperationDashboard')
            ->where('activeOperation.operation.id', $operation->id)
            ->where('editingOperation.mission.id', $operation->id)
            ->where('editingOperation.squadronId', $operation->squadron_id)
        );
    }

    public function test_member_operations_query_includes_active_operation_payload(): void
    {
        /** @var User $viewer */
        $viewer = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
        ]);

        $creator = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
        ]);

        $operation = $this->makeOperation($creator, [
            'status' => 'published',
            'visibility' => 'open',
        ]);

        $response = $this
            ->actingAs($viewer)
            ->get('/operations?operation=' . $operation->id);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Operations/OperationsIndex')
            ->where('activeOperation.operation.id', $operation->id)
        );
    }

    public function test_operation_start_redirects_back_to_dashboard_context(): void
    {
        $user = $this->actingAsDirector();

        $operation = $this->makeOperation($user, [
            'status' => 'published',
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/operations/dashboard?operation=' . $operation->id)
            ->post('/operations/' . $operation->id . '/start');

        $response
            ->assertRedirect('/operations/dashboard?operation=' . $operation->id)
            ->assertSessionHas('success', 'Operation started.');

        $this->assertDatabaseHas('operations', [
            'id' => $operation->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_operation_cancel_requires_reason_on_transition_route(): void
    {
        $user = $this->actingAsDirector();

        $operation = $this->makeOperation($user, [
            'status' => 'published',
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/operations/dashboard?operation=' . $operation->id)
            ->post('/operations/' . $operation->id . '/cancel', [
                'reason' => '',
            ]);

        $response
            ->assertRedirect('/operations/dashboard?operation=' . $operation->id)
            ->assertSessionHasErrors(['reason']);

        $this->assertDatabaseHas('operations', [
            'id' => $operation->id,
            'status' => 'published',
        ]);
    }

    public function test_operation_destroy_route_cancels_with_required_reason(): void
    {
        $user = $this->actingAsDirector();

        $operation = $this->makeOperation($user, [
            'status' => 'draft',
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/operations/dashboard?edit=' . $operation->id)
            ->delete('/operations/' . $operation->id, [
                'reason' => 'Scheduling conflict with command staff.',
            ]);

        $response
            ->assertRedirect('/operations/dashboard?edit=' . $operation->id)
            ->assertSessionHas('success', 'Operation canceled.');

        $this->assertDatabaseHas('operations', [
            'id' => $operation->id,
            'status' => 'canceled',
            'cancellation_reason' => 'Scheduling conflict with command staff.',
        ]);
    }

    public function test_admin_dashboard_includes_canceled_operations_and_reasons(): void
    {
        $user = $this->actingAsDirector();

        $operation = $this->makeOperation($user, [
            'status' => 'canceled',
            'cancellation_reason' => 'Weather window closed before launch.',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/admin/dashboard?tab=operations');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->where('canceledOperations.0.id', $operation->id)
            ->where('canceledOperations.0.cancellation_reason', 'Weather window closed before launch.')
        );
    }

    public function test_cit_creator_can_open_dashboard_editor_for_owned_squadron_operation(): void
    {
        $user = $this->actingAsCit();

        $squadron = Squadron::create([
            'name' => 'Nova Squadron',
            'slug' => 'nova-squadron',
            'status' => 'active',
        ]);

        SquadronMember::create([
            'user_id' => $user->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_MEMBER,
            'joined_at' => now(),
        ]);

        $operation = $this->makeOperation($user, [
            'squadron_id' => $squadron->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/operations/dashboard?edit=' . $operation->id);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Operations/OperationDashboard')
            ->where('editingOperation.mission.id', $operation->id)
            ->where('editingOperation.squadronId', $squadron->id)
        );
    }

    public function test_join_and_update_slot_redirect_back_to_member_page_context(): void
    {
        /** @var User $viewer */
        $viewer = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
        ]);

        $creator = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
        ]);

        $operation = $this->makeOperation($creator, [
            'status' => 'published',
            'visibility' => 'open',
            'slots' => ['Pilot', 'Gunner'],
        ]);

        $joinResponse = $this
            ->actingAs($viewer)
            ->from('/operations?operation=' . $operation->id)
            ->post('/operations/' . $operation->id . '/join', [
                'slot' => 'Pilot',
            ]);

        $joinResponse
            ->assertRedirect('/operations?operation=' . $operation->id)
            ->assertSessionHas('success', 'Joined operation.');

        /** @var OperationParticipant $participant */
        $participant = OperationParticipant::query()->firstOrFail();

        $updateResponse = $this
            ->actingAs($viewer)
            ->from('/operations?operation=' . $operation->id)
            ->post('/operations/' . $operation->id . '/participants/' . $participant->id . '/slot', [
                'slot' => 'Gunner',
            ]);

        $updateResponse
            ->assertRedirect('/operations?operation=' . $operation->id)
            ->assertSessionHas('success', 'Role updated.');

        $this->assertDatabaseHas('operation_participants', [
            'id' => $participant->id,
            'operation_id' => $operation->id,
            'user_id' => $viewer->id,
            'slot' => 'Gunner',
        ]);
    }

    private function actingAsDirector(array $attributes = []): User
    {
        $directorRole = Role::create([
            'name' => 'Director',
            'slug' => 'director',
            'description' => 'Test director role',
            'is_system' => true,
        ]);

        /** @var User $user */
        $user = User::factory()->create(array_merge([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'rank' => 'admiral',
            'rank_level' => 5,
        ], $attributes));

        $user->roles()->attach($directorRole->id);
        $user->load('roles');

        return $user;
    }

    private function actingAsCit(array $attributes = []): User
    {
        $citRole = Role::create([
            'name' => 'Commander in Training',
            'slug' => 'cit',
            'description' => 'Test CIT role',
            'is_system' => true,
        ]);

        /** @var User $user */
        $user = User::factory()->create(array_merge([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'rank' => 'cit',
            'rank_level' => 3,
        ], $attributes));

        $user->roles()->attach($citRole->id);
        $user->load('roles');

        return $user;
    }

    private function makeOperation(User $creator, array $attributes = []): Operation
    {
        $payload = array_merge([
            'created_by' => $creator->id,
            'title' => 'Stanton Patrol',
            'description' => 'Security sweep.',
            'extended_description' => 'Extended operation briefing.',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'visibility' => 'open',
            'operation_strictness' => 'normal',
            'status' => 'draft',
            'slots' => ['Pilot'],
        ], $attributes);

        $operationTypeColumn = Schema::hasColumn('operations', 'operation_type')
            ? 'operation_type'
            : 'operation_kind';

        if (! array_key_exists('operation_type', $payload) && ! array_key_exists('operation_kind', $payload)) {
            $payload[$operationTypeColumn] = 'operation';
        }

        if ($operationTypeColumn === 'operation_kind' && array_key_exists('operation_type', $payload)) {
            $payload['operation_kind'] = $payload['operation_type'];
            unset($payload['operation_type']);
        }

        if ($operationTypeColumn === 'operation_type' && array_key_exists('operation_kind', $payload)) {
            $payload['operation_type'] = $payload['operation_kind'];
            unset($payload['operation_kind']);
        }

        $operation = new Operation();
        $operation->forceFill($payload);
        $operation->save();

        return $operation->fresh();
    }
}
