<?php

namespace Tests\Unit\Domain\AccessControl;

use App\Domain\AccessControl\OperationAccessService;
use App\Domain\Squadrons\Enums\SquadronMembershipStatus;
use App\Domain\Squadrons\Enums\SquadronRole;
use App\Models\Operation;
use App\Models\Role;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lieutenant_creator_can_update_owned_squadron_operation(): void
    {
        $service = app(OperationAccessService::class);
        $lieutenantRole = Role::create(['name' => 'Lieutenant', 'slug' => 'lieutenant']);
        $user = User::factory()->create();
        $user->roles()->attach($lieutenantRole->id);
        $user->load('roles');

        $squadron = Squadron::create([
            'name' => 'Hammer',
            'slug' => 'hammer',
            'status' => 'active',
        ]);

        SquadronMember::create([
            'user_id' => $user->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMembershipStatus::Active->value,
            'role' => SquadronRole::Lieutenant->value,
            'joined_at' => now(),
        ]);

        $operation = Operation::create([
            'squadron_id' => $squadron->id,
            'created_by' => $user->id,
            'title' => 'Hammerline',
            'starts_at' => now()->addDay(),
            'status' => 'draft',
        ]);

        $this->assertTrue($service->canUpdateOperation($user, $operation));
    }

    public function test_non_member_cannot_view_squadron_only_operation_without_global_permission(): void
    {
        $service = app(OperationAccessService::class);
        $user = User::factory()->create();
        $creator = User::factory()->create();
        $squadron = Squadron::create([
            'name' => 'Ghost',
            'slug' => 'ghost',
            'status' => 'active',
        ]);

        $operation = Operation::create([
            'squadron_id' => $squadron->id,
            'created_by' => $creator->id,
            'title' => 'Ghost Net',
            'starts_at' => now()->addDay(),
            'status' => 'published',
            'visibility' => 'squadron',
        ]);

        $this->assertFalse($service->canViewOperation($user, $operation));
    }

    public function test_member_can_view_squadron_only_operation_when_selected_squadron_name_matches_membership(): void
    {
        $service = app(OperationAccessService::class);
        $user = User::factory()->create();
        $creator = User::factory()->create();
        $squadron = Squadron::create([
            'name' => 'Ghost',
            'slug' => 'ghost',
            'status' => 'active',
        ]);

        SquadronMember::create([
            'user_id' => $user->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMembershipStatus::Active->value,
            'role' => SquadronRole::Member->value,
            'joined_at' => now(),
        ]);

        $operation = Operation::create([
            'created_by' => $creator->id,
            'title' => 'Ghost Net',
            'squadron_name' => 'Ghost, Nova',
            'starts_at' => now()->addDay(),
            'status' => 'published',
            'visibility' => 'squadron',
        ]);

        $this->assertTrue($service->canViewOperation($user, $operation));
    }

    public function test_creator_can_view_and_assign_operation_slots(): void
    {
        $service = app(OperationAccessService::class);
        $creator = User::factory()->create();
        $operation = Operation::create([
            'created_by' => $creator->id,
            'title' => 'Creator Owned Operation',
            'starts_at' => now()->addDay(),
            'status' => 'published',
        ]);

        $this->assertTrue($service->canViewOperationSlots($creator, $operation));
        $this->assertTrue($service->canAssignOperationSlots($creator, $operation));
        $this->assertTrue($service->canManageOperationMembers($creator, $operation));
        $this->assertTrue($service->canAdjustOperationStats($creator, $operation));
    }

    public function test_director_can_view_and_assign_operation_slots(): void
    {
        $service = app(OperationAccessService::class);
        $directorRole = Role::create(['name' => 'Director', 'slug' => 'director']);
        $director = User::factory()->create();
        $director->roles()->attach($directorRole->id);
        $director->load('roles');

        $creator = User::factory()->create();
        $operation = Operation::create([
            'created_by' => $creator->id,
            'title' => 'Director Override Operation',
            'starts_at' => now()->addDay(),
            'status' => 'published',
        ]);

        $this->assertTrue($service->canViewOperationSlots($director, $operation));
        $this->assertTrue($service->canAssignOperationSlots($director, $operation));
    }

    public function test_squadron_leader_can_fully_manage_their_squadron_operation(): void
    {
        $service = app(OperationAccessService::class);
        $leader = User::factory()->create();
        $creator = User::factory()->create();
        $squadron = Squadron::create([
            'name' => 'Signal',
            'slug' => 'signal',
            'status' => 'active',
            'leader_id' => $leader->id,
        ]);

        SquadronMember::create([
            'user_id' => $leader->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMembershipStatus::Active->value,
            'role' => SquadronRole::Leader->value,
            'joined_at' => now(),
        ]);

        $operation = Operation::create([
            'squadron_id' => $squadron->id,
            'created_by' => $creator->id,
            'title' => 'Leader Cannot Reassign',
            'starts_at' => now()->addDay(),
            'status' => 'published',
        ]);

        $this->assertTrue($service->canUpdateOperation($leader, $operation));
        $this->assertTrue($service->canManageOperationMembers($leader, $operation));
        $this->assertTrue($service->canViewOperationSlots($leader, $operation));
        $this->assertTrue($service->canAssignOperationSlots($leader, $operation));
        $this->assertTrue($service->canAdjustOperationStats($leader, $operation));
    }

    public function test_squadron_lieutenant_can_fully_manage_their_squadron_operation(): void
    {
        $service = app(OperationAccessService::class);
        $lieutenant = User::factory()->create();
        $creator = User::factory()->create();
        $squadron = Squadron::create([
            'name' => 'Relay',
            'slug' => 'relay',
            'status' => 'active',
        ]);

        SquadronMember::create([
            'user_id' => $lieutenant->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMembershipStatus::Active->value,
            'role' => SquadronRole::Lieutenant->value,
            'joined_at' => now(),
        ]);

        $operation = Operation::create([
            'squadron_id' => $squadron->id,
            'created_by' => $creator->id,
            'title' => 'Relay Command',
            'starts_at' => now()->addDay(),
            'status' => 'published',
        ]);

        $this->assertTrue($service->canUpdateOperation($lieutenant, $operation));
        $this->assertTrue($service->canManageOperationMembers($lieutenant, $operation));
        $this->assertTrue($service->canViewOperationSlots($lieutenant, $operation));
        $this->assertTrue($service->canAssignOperationSlots($lieutenant, $operation));
        $this->assertTrue($service->canAdjustOperationStats($lieutenant, $operation));
    }

    public function test_squadron_operation_creator_without_command_role_cannot_fully_manage_it(): void
    {
        $service = app(OperationAccessService::class);
        $creator = User::factory()->create();
        $squadron = Squadron::create([
            'name' => 'Atlas',
            'slug' => 'atlas',
            'status' => 'active',
        ]);

        SquadronMember::create([
            'user_id' => $creator->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMembershipStatus::Active->value,
            'role' => SquadronRole::Member->value,
            'joined_at' => now(),
        ]);

        $operation = Operation::create([
            'squadron_id' => $squadron->id,
            'created_by' => $creator->id,
            'title' => 'Atlas Briefing',
            'starts_at' => now()->addDay(),
            'status' => 'published',
        ]);

        $this->assertFalse($service->canUpdateOperation($creator, $operation));
        $this->assertFalse($service->canManageOperationMembers($creator, $operation));
        $this->assertFalse($service->canViewOperationSlots($creator, $operation));
        $this->assertFalse($service->canAssignOperationSlots($creator, $operation));
        $this->assertFalse($service->canAdjustOperationStats($creator, $operation));
    }

    public function test_completed_squadron_operation_uses_owning_squadron_command_for_after_action_access(): void
    {
        $service = app(OperationAccessService::class);
        $lieutenant = User::factory()->create();
        $creator = User::factory()->create();
        $squadron = Squadron::create([
            'name' => 'Nova',
            'slug' => 'nova',
            'status' => 'active',
        ]);

        SquadronMember::create([
            'user_id' => $lieutenant->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMembershipStatus::Active->value,
            'role' => SquadronRole::Lieutenant->value,
            'joined_at' => now(),
        ]);

        $operation = Operation::create([
            'squadron_id' => $squadron->id,
            'created_by' => $creator->id,
            'title' => 'Nova Debrief',
            'starts_at' => now()->subHours(2),
            'status' => 'completed',
        ]);

        $this->assertTrue($service->canManageAfterActionReport($lieutenant, $operation));
        $this->assertFalse($service->canManageAfterActionReport($creator, $operation));
    }
}
