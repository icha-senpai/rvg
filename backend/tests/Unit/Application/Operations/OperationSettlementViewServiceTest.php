<?php

namespace Tests\Unit\Application\Operations;

use App\Application\Operations\OperationMemberPayloadService;
use App\Application\Operations\OperationSettlementViewService;
use App\Domain\AccessControl\AccessService;
use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\OperationSettlement;
use App\Models\Squadron;
use App\Models\User;
use App\Services\LedgerReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OperationSettlementViewServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_payload_maps_recipient_and_loot_labels_from_eligible_destinations_and_options(): void
    {
        $viewer = User::factory()->create();
        $member = $this->member(['rsi_handle' => 'PaidPilot']);
        $squadron = $this->squadron('Aurora Wing');
        $operation = $this->completedOperation([
            'squadron_id' => $squadron->id,
            'squadron_name' => $squadron->name,
            'after_action_attendance_user_ids' => [$member->id],
        ]);
        $settlement = OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [
                [
                    'row_key' => 'member-pay',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $member->id,
                    'amount' => 5000,
                ],
                [
                    'row_key' => 'org-pay',
                    'recipient_type' => 'organization',
                    'amount' => 800,
                ],
            ],
            'loot_rows' => [
                [
                    'row_key' => 'loot-1',
                    'source_type' => 'commodity',
                    'uex_reference_id' => 9001,
                    'recipient_type' => 'squadron',
                    'recipient_squadron_id' => $squadron->id,
                    'quantity' => 6,
                ],
            ],
        ]);
        $settlement->setRelation('finalizedBy', $member);
        $operation->setRelation('settlement', $settlement);

        $access = Mockery::mock(AccessService::class);
        $ledgerReferences = Mockery::mock(LedgerReferenceService::class);
        $members = new OperationMemberPayloadService();
        $service = new OperationSettlementViewService($access, $ledgerReferences, $members);

        $payload = $service->payload(
            $operation,
            $viewer,
            true,
            [
                'commodities' => [['value' => '9001', 'label' => 'Agricium']],
                'items' => [],
                'components' => [],
            ]
        );

        $this->assertTrue($payload['is_available']);
        $this->assertTrue($payload['permissions']['can_manage']);
        $this->assertSame('PaidPilot', $payload['money_rows'][0]['recipient_label']);
        $this->assertSame('Horizon Treasury', $payload['money_rows'][1]['recipient_label']);
        $this->assertSame('Agricium', $payload['loot_rows'][0]['reference_label']);
        $this->assertSame('Aurora Wing Squadron Assets & Funds', $payload['loot_rows'][0]['recipient_label']);
        $this->assertSame('squadron', $payload['eligible_recipients'][0]['recipient_type']);
        $this->assertSame('organization', $payload['eligible_recipients'][1]['recipient_type']);
        $this->assertSame('member', $payload['eligible_recipients'][2]['recipient_type']);
    }

    public function test_prep_payload_excludes_signed_off_participants_and_includes_activity_metadata(): void
    {
        $activeMember = $this->member(['rsi_handle' => 'ReadyPilot']);
        $signedOffMember = $this->member(['rsi_handle' => 'SignedOffPilot']);
        $editor = $this->member(['rsi_handle' => 'TreasuryLead']);
        $operation = $this->publishedOperation();

        OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $activeMember->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);

        OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $signedOffMember->id,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_OFF_BEFORE_START,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'signed_off_at' => now()->subMinutes(5),
        ]);

        $settlement = OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'prep_money_rows' => [
                [
                    'row_key' => 'prep-member',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $activeMember->id,
                    'amount' => 3000,
                ],
            ],
            'prep_money_rows_updated_by_user_id' => $editor->id,
            'prep_money_rows_updated_at' => now()->subMinute(),
        ]);
        $settlement->setRelation('prepUpdatedBy', $editor);
        $operation->setRelation('settlement', $settlement);
        $operation->load('participants.user');

        $service = new OperationSettlementViewService(
            Mockery::mock(AccessService::class),
            Mockery::mock(LedgerReferenceService::class),
            new OperationMemberPayloadService()
        );

        $payload = $service->prepPayload($operation);

        $memberRecipients = collect($payload['eligible_recipients'])
            ->where('recipient_type', 'member')
            ->pluck('recipient_user_id')
            ->all();

        $this->assertTrue($payload['is_available']);
        $this->assertSame([ $activeMember->id ], $memberRecipients);
        $this->assertSame('ReadyPilot', $payload['money_rows'][0]['recipient_label']);
        $this->assertSame('TreasuryLead', $payload['activity']['updated_by']);
        $this->assertNotNull($payload['activity']['updated_at']);
    }

    public function test_payload_returns_unavailable_shape_when_storage_is_not_supported(): void
    {
        $service = new class(
            Mockery::mock(AccessService::class),
            Mockery::mock(LedgerReferenceService::class),
            new OperationMemberPayloadService()
        ) extends OperationSettlementViewService {
            public function supportsStorage(): bool
            {
                return false;
            }
        };

        $payload = $service->payload($this->completedOperation());
        $prepPayload = $service->prepPayload($this->publishedOperation());

        $this->assertFalse($payload['is_available']);
        $this->assertSame([], $payload['money_rows']);
        $this->assertSame([], $payload['eligible_recipients']);
        $this->assertFalse($prepPayload['is_available']);
        $this->assertSame([], $prepPayload['money_rows']);
    }

    private function completedOperation(array $attributes = []): Operation
    {
        $operation = new Operation();
        $operation->forceFill(array_merge([
            'created_by' => User::factory()->create()->id,
            'title' => 'Settlement view test',
            'description' => 'Completed operation.',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'status' => 'completed',
            'after_action_attendance_user_ids' => [],
            'after_action_no_show_user_ids' => [],
            'after_action_signed_off_early_user_ids' => [],
            'after_action_excused_user_ids' => [],
        ], $attributes));
        $operation->save();

        return $operation->fresh(['squadron']);
    }

    private function publishedOperation(array $attributes = []): Operation
    {
        $operation = new Operation();
        $operation->forceFill(array_merge([
            'created_by' => User::factory()->create()->id,
            'title' => 'Prep view test',
            'description' => 'Published operation.',
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(4),
            'status' => 'published',
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

    private function squadron(string $name): Squadron
    {
        return Squadron::query()->create([
            'name' => $name,
            'slug' => str($name)->slug('-')->value(),
            'status' => 'active',
            'leader_id' => $this->member()->id,
        ]);
    }
}
