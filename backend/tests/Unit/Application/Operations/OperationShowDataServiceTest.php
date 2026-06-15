<?php

namespace Tests\Unit\Application\Operations;

use App\Application\Operations\OperationMemberPayloadService;
use App\Application\Operations\OperationParticipantPayloadService;
use App\Application\Operations\OperationSettlementViewService;
use App\Application\Operations\OperationShowDataService;
use App\Domain\AccessControl\AccessService;
use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\OperationRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OperationShowDataServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_build_hides_general_assignments_but_keeps_current_participant_details_and_filters_signed_off_members(): void
    {
        $viewer = $this->member(['rsi_handle' => 'ViewerLead']);
        $attendee = $this->member(['rsi_handle' => 'AttendedWing']);
        $operation = $this->publishedOperation([
            'after_action_attendance_user_ids' => [$attendee->id],
        ]);

        $this->participant($operation, $viewer, [
            'slot' => 'Leader',
            'role_name' => 'leader',
            'role_display_name' => 'Leader',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
        ]);

        $visibleParticipant = $this->participant($operation, $this->member(['rsi_handle' => 'MedicPilot']), [
            'slot' => 'Medic',
            'role_name' => 'medic',
            'role_display_name' => 'Medic',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
        ]);

        $signedOffParticipant = $this->participant($operation, $this->member(['rsi_handle' => 'SignedOffPilot']), [
            'slot' => 'Gunner',
            'role_name' => 'gunner',
            'role_display_name' => 'Gunner',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_OFF_BEFORE_START,
            'signed_off_at' => now()->subMinutes(15),
        ]);

        $access = Mockery::mock(AccessService::class);
        $access->shouldReceive('canViewOperationSlots')->once()->andReturn(false);
        $access->shouldReceive('canAssignOperationSlots')->once()->andReturn(false);
        $access->shouldReceive('canManageAfterActionReport')->once()->andReturn(false);

        $service = new OperationShowDataService(
            $access,
            new OperationMemberPayloadService(),
            app(OperationParticipantPayloadService::class),
            $this->settlementStub(['is_available' => false, 'money_rows' => []])
        );

        $payload = $service->build($operation->fresh(), $viewer);

        $this->assertCount(2, $payload['participants']);
        $this->assertSame([$viewer->id, $visibleParticipant->user_id], collect($payload['participants'])->pluck('user.id')->all());
        $this->assertNotContains($signedOffParticipant->user_id, collect($payload['participants'])->pluck('user.id')->all());
        $this->assertNull($payload['participants'][0]['slot']);
        $this->assertSame('Leader', $payload['currentParticipant']['slot']);
        $this->assertSame('leader', $payload['currentParticipant']['role']['role_name']);
        $this->assertSame(2, $payload['operation']['participants_count']);
        $this->assertSame('AttendedWing', $payload['operation']['after_action_attendance'][0]['rsi_handle']);
        $this->assertFalse($payload['operation']['permissions']['can_view_slots']);
        $this->assertFalse($payload['operation']['permissions']['can_manage_aar']);
        $this->assertSame([], $payload['participantsBySlot']);
        $this->assertSame([], $payload['unassignedParticipants']);
        $this->assertSame([], $payload['verifiedMembers']);
    }

    public function test_build_groups_slot_assignments_and_returns_verified_members_for_managers(): void
    {
        $viewer = $this->member(['rsi_handle' => 'RosterLead']);
        $visibleVerified = $this->member(['rsi_handle' => 'AlphaPilot']);
        $ignoredUnverified = User::factory()->create(['rsi_handle' => 'GhostPilot']);
        $ignoredInactive = $this->member([
            'rsi_handle' => 'InactivePilot',
            'global_status' => User::STATUS_PENDING,
        ]);

        $operation = $this->publishedOperation();

        $assignedParticipant = $this->participant($operation, $visibleVerified, [
            'slot' => 'Pilot',
            'role_name' => 'pilot',
            'role_display_name' => 'Pilot',
        ]);

        $unassignedParticipant = $this->participant($operation, $viewer, [
            'slot' => null,
            'role_name' => 'reserve',
            'role_display_name' => 'Reserve',
        ]);

        $access = Mockery::mock(AccessService::class);
        $access->shouldReceive('canViewOperationSlots')->once()->andReturn(true);
        $access->shouldReceive('canAssignOperationSlots')->once()->andReturn(true);
        $access->shouldReceive('canManageAfterActionReport')->once()->andReturn(true);

        $service = new OperationShowDataService(
            $access,
            new OperationMemberPayloadService(),
            app(OperationParticipantPayloadService::class),
            $this->settlementStub([
                'is_available' => true,
                'money_rows' => [['row_key' => 'prep-1']],
            ])
        );

        $payload = $service->build($operation->fresh(), $viewer);

        $this->assertTrue($payload['operation']['permissions']['can_view_slots']);
        $this->assertTrue($payload['operation']['permissions']['can_assign_slots']);
        $this->assertTrue($payload['operation']['permissions']['can_manage_settlement']);
        $this->assertContains('Pilot', collect($payload['participants'])->pluck('slot')->all());
        $this->assertSame([$assignedParticipant->id], collect($payload['participantsBySlot']['Pilot'])->pluck('id')->all());
        $this->assertSame([$unassignedParticipant->id], collect($payload['unassignedParticipants'])->pluck('id')->all());
        $this->assertSame([['row_key' => 'prep-1']], $payload['operation']['operation_settlement']['money_rows']);

        $verifiedHandles = collect($payload['verifiedMembers'])->pluck('rsi_handle')->all();

        $this->assertContains('AlphaPilot', $verifiedHandles);
        $this->assertContains('RosterLead', $verifiedHandles);
        $this->assertNotContains('GhostPilot', $verifiedHandles);
        $this->assertNotContains($ignoredInactive->rsi_handle, $verifiedHandles);
        $this->assertNotContains($ignoredUnverified->rsi_handle, $verifiedHandles);
    }

    private function publishedOperation(array $attributes = []): Operation
    {
        $operation = new Operation();
        $operation->forceFill(array_merge([
            'created_by' => $this->member()->id,
            'title' => 'Show data test',
            'description' => 'Operation show payload.',
            'status' => 'published',
            'visibility' => 'open',
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(4),
            'after_action_attendance_user_ids' => [],
            'after_action_no_show_user_ids' => [],
            'after_action_signed_off_early_user_ids' => [],
            'after_action_excused_user_ids' => [],
            'slots' => [],
        ], $attributes));
        $operation->save();

        return $operation->fresh();
    }

    private function participant(Operation $operation, User $user, array $attributes = []): OperationParticipant
    {
        $slot = $attributes['slot'] ?? 'Pilot';
        $role = OperationRole::query()->create([
            'operation_id' => $operation->id,
            'role_name' => $attributes['role_name'] ?? strtolower((string) ($slot ?: 'reserve')),
            'role_display_name' => $attributes['role_display_name'] ?? ($slot ?: 'Reserve'),
            'capacity' => 2,
        ]);

        return OperationParticipant::query()->create(array_merge([
            'operation_id' => $operation->id,
            'user_id' => $user->id,
            'operation_role_id' => $role->id,
            'slot' => $slot,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'notes' => 'Bring supplies',
        ], $attributes))->fresh(['user', 'role']);
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
        ], $attributes));
    }

    private function settlementStub(array $payload): OperationSettlementViewService
    {
        return new class($payload) extends OperationSettlementViewService {
            public function __construct(private array $payload)
            {
            }

            public function supportsStorage(): bool
            {
                return false;
            }

            public function payload(
                Operation $operation,
                ?User $viewer = null,
                ?bool $canManage = null,
                ?array $lootOptions = null,
                bool $includeLootOptions = true
            ): array {
                return $this->payload + [
                    'eligible_recipients' => [],
                    'loot_rows' => [],
                    'permissions' => [
                        'can_manage' => (bool) $canManage,
                    ],
                ];
            }

            public function lootOptions(): array
            {
                return [
                    'commodities' => [],
                    'items' => [],
                    'components' => [],
                ];
            }
        };
    }
}
