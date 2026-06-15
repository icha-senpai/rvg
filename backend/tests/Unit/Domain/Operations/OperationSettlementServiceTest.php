<?php

namespace Tests\Unit\Domain\Operations;

use App\Domain\Operations\Enums\OperationStatus;
use App\Domain\Operations\Services\OperationSettlementService;
use App\Models\LedgerInventoryItem;
use App\Models\LedgerTransaction;
use App\Models\LedgerTransferRequest;
use App\Models\Operation;
use App\Models\OperationSettlement;
use App\Models\Squadron;
use App\Models\User;
use App\Models\WipeCycle;
use App\Services\LedgerCycleService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class OperationSettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_seed_draft_from_prep_copies_eligible_rows_into_money_rows(): void
    {
        $member = $this->member(['rsi_handle' => 'CrewPilot']);
        $squadron = Squadron::query()->create([
            'name' => 'Nova Wing',
            'slug' => 'nova-wing',
            'status' => 'active',
            'leader_id' => $this->member()->id,
        ]);
        $operation = $this->completedOperation([
            'squadron_id' => $squadron->id,
            'squadron_name' => $squadron->name,
            'after_action_attendance_user_ids' => [$member->id],
        ]);

        OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
            'prep_money_rows' => [
                [
                    'row_key' => 'prep-member',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $member->id,
                    'amount' => 4200,
                    'notes' => 'Crew cut',
                ],
                [
                    'row_key' => 'prep-squadron',
                    'recipient_type' => 'squadron',
                    'recipient_squadron_id' => $squadron->id,
                    'amount' => 1800,
                    'notes' => 'Wing reserve',
                ],
            ],
        ]);

        $service = new OperationSettlementService(Mockery::mock(LedgerService::class));
        $updated = $service->seedDraftFromPrep($operation);
        $settlement = $updated->settlement()->firstOrFail();

        $this->assertCount(2, $settlement->money_rows ?? []);
        $this->assertSame('member', $settlement->money_rows[0]['recipient_type']);
        $this->assertSame($member->id, $settlement->money_rows[0]['recipient_user_id']);
        $this->assertSame(4200, $settlement->money_rows[0]['amount']);
        $this->assertSame('squadron', $settlement->money_rows[1]['recipient_type']);
        $this->assertSame($squadron->id, $settlement->money_rows[1]['recipient_squadron_id']);
    }

    public function test_seed_draft_from_prep_turns_ineligible_targets_into_review_rows(): void
    {
        $ineligibleUser = $this->member(['rsi_handle' => 'OffRosterPilot']);
        $operation = $this->completedOperation([
            'after_action_attendance_user_ids' => [],
        ]);

        OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
            'prep_money_rows' => [
                [
                    'row_key' => 'prep-review',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $ineligibleUser->id,
                    'amount' => 2500,
                    'notes' => 'Needs reassignment',
                ],
            ],
        ]);

        $service = new OperationSettlementService(Mockery::mock(LedgerService::class));
        $updated = $service->seedDraftFromPrep($operation);
        $settlement = $updated->settlement()->firstOrFail();
        $row = $settlement->money_rows[0] ?? null;

        $this->assertNotNull($row);
        $this->assertNull($row['recipient_type']);
        $this->assertNull($row['recipient_user_id']);
        $this->assertNull($row['recipient_squadron_id']);
        $this->assertSame(2500, $row['amount']);
        $this->assertStringStartsWith('Prep target requires review: OffRosterPilot', (string) $row['notes']);
        $this->assertStringContainsString('Needs reassignment', (string) $row['notes']);
    }

    public function test_seed_draft_from_prep_does_not_overwrite_existing_money_rows(): void
    {
        $member = $this->member();
        $operation = $this->completedOperation([
            'after_action_attendance_user_ids' => [$member->id],
        ]);

        OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [
                [
                    'row_key' => 'existing',
                    'recipient_type' => 'organization',
                    'recipient_user_id' => null,
                    'recipient_squadron_id' => null,
                    'amount' => 999,
                    'notes' => 'Existing settlement draft',
                ],
            ],
            'loot_rows' => [],
            'prep_money_rows' => [
                [
                    'row_key' => 'prep-member',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $member->id,
                    'amount' => 4200,
                ],
            ],
        ]);

        $service = new OperationSettlementService(Mockery::mock(LedgerService::class));
        $updated = $service->seedDraftFromPrep($operation);
        $settlement = $updated->settlement()->firstOrFail();

        $this->assertCount(1, $settlement->money_rows ?? []);
        $this->assertSame('existing', $settlement->money_rows[0]['row_key']);
        $this->assertSame(999, $settlement->money_rows[0]['amount']);
    }

    public function test_upsert_draft_drops_blank_rows_and_preserves_review_rows_in_mixed_payload(): void
    {
        $actor = $this->member(['rsi_handle' => 'SettlementLead']);
        $member = $this->member(['rsi_handle' => 'CrewPilot']);
        $squadron = Squadron::query()->create([
            'name' => 'Nova Wing',
            'slug' => 'nova-wing-mixed-draft',
            'status' => 'active',
            'leader_id' => $actor->id,
        ]);
        $operation = $this->completedOperation([
            'squadron_id' => $squadron->id,
            'squadron_name' => $squadron->name,
            'after_action_attendance_user_ids' => [$member->id],
        ]);

        $this->seedUexSettlementReferences();

        $service = new OperationSettlementService(Mockery::mock(LedgerService::class));
        $settlement = $service->upsertDraft($actor, $operation, [
            'money_rows' => [
                [
                    'row_key' => 'blank-money',
                    'recipient_type' => null,
                    'recipient_user_id' => null,
                    'recipient_squadron_id' => null,
                    'amount' => null,
                    'notes' => '   ',
                ],
                [
                    'row_key' => 'review-money',
                    'amount' => 2500,
                    'notes' => ' Needs review ',
                ],
                [
                    'row_key' => 'member-money',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $member->id,
                    'amount' => 4200,
                    'notes' => ' Crew cut ',
                ],
            ],
            'loot_rows' => [
                [
                    'row_key' => 'blank-loot',
                    'source_type' => null,
                    'uex_reference_id' => null,
                    'recipient_type' => null,
                    'quantity' => null,
                    'unit_label' => ' ',
                    'notes' => ' ',
                ],
                [
                    'row_key' => 'review-loot',
                    'source_type' => 'component',
                    'uex_reference_id' => 9101,
                    'quantity' => 2,
                    'notes' => ' Salvage module ',
                ],
                [
                    'row_key' => 'org-loot',
                    'source_type' => 'commodity',
                    'uex_reference_id' => 9001,
                    'recipient_type' => 'organization',
                    'quantity' => 6,
                    'unit_label' => '',
                    'notes' => ' Cargo reserve ',
                ],
            ],
        ]);

        $this->assertCount(2, $settlement->money_rows ?? []);
        $this->assertSame('review-money', $settlement->money_rows[0]['row_key']);
        $this->assertNull($settlement->money_rows[0]['recipient_type']);
        $this->assertSame(2500, $settlement->money_rows[0]['amount']);
        $this->assertSame('Needs review', $settlement->money_rows[0]['notes']);
        $this->assertSame('member', $settlement->money_rows[1]['recipient_type']);
        $this->assertSame($member->id, $settlement->money_rows[1]['recipient_user_id']);
        $this->assertSame('Crew cut', $settlement->money_rows[1]['notes']);

        $this->assertCount(2, $settlement->loot_rows ?? []);
        $this->assertSame('review-loot', $settlement->loot_rows[0]['row_key']);
        $this->assertSame('component', $settlement->loot_rows[0]['source_type']);
        $this->assertSame('item', $settlement->loot_rows[0]['uex_reference_type']);
        $this->assertSame(9101, $settlement->loot_rows[0]['uex_reference_id']);
        $this->assertSame('Atlas Drive', $settlement->loot_rows[0]['reference_label']);
        $this->assertNull($settlement->loot_rows[0]['recipient_type']);
        $this->assertNull($settlement->loot_rows[0]['unit_label']);
        $this->assertSame('Salvage module', $settlement->loot_rows[0]['notes']);
        $this->assertSame('org-loot', $settlement->loot_rows[1]['row_key']);
        $this->assertSame('organization', $settlement->loot_rows[1]['recipient_type']);
        $this->assertSame('commodity', $settlement->loot_rows[1]['uex_reference_type']);
        $this->assertSame('Agricium', $settlement->loot_rows[1]['reference_label']);
        $this->assertNull($settlement->loot_rows[1]['unit_label']);
        $this->assertSame('Cargo reserve', $settlement->loot_rows[1]['notes']);
    }

    public function test_finalize_saved_mixed_draft_rejects_unresolved_review_loot_rows(): void
    {
        $actor = $this->member(['rsi_handle' => 'SettlementLead']);
        $member = $this->member(['rsi_handle' => 'CrewPilot']);
        $operation = $this->completedOperation([
            'after_action_attendance_user_ids' => [$member->id],
        ]);

        $this->seedUexSettlementReferences();

        OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [
                [
                    'row_key' => 'member-pay',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $member->id,
                    'amount' => 4200,
                    'notes' => 'Crew cut',
                ],
            ],
            'loot_rows' => [
                [
                    'row_key' => 'review-loot',
                    'source_type' => 'component',
                    'uex_reference_type' => 'item',
                    'uex_reference_id' => 9101,
                    'reference_label' => 'Atlas Drive',
                    'recipient_type' => null,
                    'recipient_user_id' => null,
                    'recipient_squadron_id' => null,
                    'quantity' => 2,
                    'unit_label' => null,
                    'notes' => 'Needs routing',
                ],
            ],
        ]);

        $service = new OperationSettlementService(Mockery::mock(LedgerService::class));

        $exception = $this->captureValidationException(fn () => $service->finalize($actor, $operation, []));

        $this->assertSame(
            'Each loot row needs a destination before the settlement can be finalized.',
            $exception->errors()['loot_rows'][0] ?? null,
        );
    }

    public function test_reopen_rejects_missing_settlements(): void
    {
        $actor = $this->member();
        $operation = $this->completedOperation();

        $service = new OperationSettlementService(Mockery::mock(LedgerService::class));

        $exception = $this->captureValidationException(fn () => $service->reopen($actor, $operation));

        $this->assertSame(
            'There is no operation settlement to reopen yet.',
            $exception->errors()['settlement'][0] ?? null,
        );
    }

    public function test_reopen_rejects_unfinalized_settlements(): void
    {
        $actor = $this->member();
        $operation = $this->completedOperation();

        OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
            'finalized_at' => null,
            'finalized_by_user_id' => null,
        ]);

        $service = new OperationSettlementService(Mockery::mock(LedgerService::class));

        $exception = $this->captureValidationException(fn () => $service->reopen($actor, $operation->fresh()));

        $this->assertSame(
            'This settlement is not finalized yet.',
            $exception->errors()['settlement'][0] ?? null,
        );
    }

    public function test_reopen_rejects_settlements_with_inventory_transfer_descendants(): void
    {
        $actor = $this->member();
        $owner = $this->member();
        $operation = $this->completedOperation();
        $settlement = OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
            'finalized_by_user_id' => $actor->id,
            'finalized_at' => now()->subHour(),
        ]);
        $wipe = $this->currentWipeCycle();

        $settlementItem = LedgerInventoryItem::query()->create([
            'user_id' => $owner->id,
            'squadron_id' => null,
            'is_org_owned' => false,
            'wipe_cycle_id' => $wipe->id,
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'source_type' => 'commodity',
            'uex_reference_type' => 'commodity',
            'uex_reference_id' => 9001,
            'category' => 'Commodity',
            'quantity' => 8,
            'unit_label' => 'SCU',
            'currency' => 'aUEC',
            'status' => 'stored',
            'provenance_locked' => true,
            'acquired_at' => now()->subHour(),
        ]);

        LedgerInventoryItem::query()->create([
            'user_id' => $owner->id,
            'squadron_id' => null,
            'is_org_owned' => false,
            'wipe_cycle_id' => $wipe->id,
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => null,
            'transfer_origin_item_id' => $settlementItem->id,
            'source_type' => 'commodity',
            'uex_reference_type' => 'commodity',
            'uex_reference_id' => 9001,
            'category' => 'Commodity',
            'quantity' => 2,
            'unit_label' => 'SCU',
            'currency' => 'aUEC',
            'status' => 'stored',
            'provenance_locked' => true,
            'acquired_at' => now()->subMinutes(30),
        ]);

        $service = new OperationSettlementService(Mockery::mock(LedgerService::class));

        $exception = $this->captureValidationException(fn () => $service->reopen($actor, $operation->fresh()));

        $this->assertSame(
            'This settlement cannot be reopened because some of its loot receipts have already been moved into other ledgers. Those downstream inventory moves need to stay intact.',
            $exception->errors()['settlement'][0] ?? null,
        );
    }

    public function test_reopen_rejects_settlements_after_downstream_funds_transfer_activity(): void
    {
        $actor = $this->member();
        $recipient = $this->member();
        $operation = $this->completedOperation();
        $settlement = OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
            'finalized_by_user_id' => $actor->id,
            'finalized_at' => now()->subHour(),
        ]);
        $wipe = $this->currentWipeCycle();
        $account = app(LedgerCycleService::class)->defaultAccountFor($recipient);

        LedgerTransaction::query()->create([
            'user_id' => $recipient->id,
            'squadron_id' => null,
            'is_org_owned' => false,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 6000,
            'currency' => 'aUEC',
            'source_type' => 'operation_settlement',
            'description' => 'Settlement payout',
            'transaction_date' => now()->subHour(),
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'provenance_locked' => true,
        ]);

        LedgerTransferRequest::query()->create([
            'transfer_kind' => 'funds',
            'status' => 'approved',
            'requested_by_user_id' => $recipient->id,
            'wipe_cycle_id' => $wipe->id,
            'source_user_id' => $recipient->id,
            'source_squadron_id' => null,
            'source_is_org_owned' => false,
            'destination_user_id' => null,
            'destination_squadron_id' => null,
            'destination_is_org_owned' => true,
            'destination_is_external' => false,
            'amount' => 1000,
            'currency' => 'aUEC',
            'description' => 'Move payout onward',
            'transaction_date' => now()->subMinutes(30),
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        $service = new OperationSettlementService(Mockery::mock(LedgerService::class));

        $exception = $this->captureValidationException(fn () => $service->reopen($actor, $operation->fresh()));

        $this->assertSame(
            'This settlement cannot be reopened because one of the receiving ledgers already started moving funds after the payout landed. Reopening now could break later transfer history.',
            $exception->errors()['settlement'][0] ?? null,
        );
    }

    public function test_reopen_rejects_settlements_after_downstream_squadron_funds_transfer_activity(): void
    {
        $actor = $this->member();
        $squadron = Squadron::query()->create([
            'name' => 'Nova Wing',
            'slug' => 'nova-wing',
            'status' => 'active',
            'leader_id' => $actor->id,
        ]);
        $operation = $this->completedOperation([
            'squadron_id' => $squadron->id,
            'squadron_name' => $squadron->name,
        ]);
        $settlement = OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
            'finalized_by_user_id' => $actor->id,
            'finalized_at' => now()->subHour(),
        ]);
        $wipe = $this->currentWipeCycle();
        $account = app(LedgerCycleService::class)->defaultSquadronAccountFor($squadron, $actor);

        LedgerTransaction::query()->create([
            'user_id' => $actor->id,
            'squadron_id' => $squadron->id,
            'is_org_owned' => false,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 6000,
            'currency' => 'aUEC',
            'source_type' => 'operation_settlement',
            'description' => 'Settlement payout',
            'transaction_date' => now()->subHour(),
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'provenance_locked' => true,
        ]);

        LedgerTransferRequest::query()->create([
            'transfer_kind' => 'funds',
            'status' => 'approved',
            'requested_by_user_id' => $actor->id,
            'wipe_cycle_id' => $wipe->id,
            'source_user_id' => null,
            'source_squadron_id' => $squadron->id,
            'source_is_org_owned' => false,
            'destination_user_id' => null,
            'destination_squadron_id' => null,
            'destination_is_org_owned' => true,
            'destination_is_external' => false,
            'amount' => 1000,
            'currency' => 'aUEC',
            'description' => 'Move squadron payout onward',
            'transaction_date' => now()->subMinutes(30),
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        $service = new OperationSettlementService(Mockery::mock(LedgerService::class));

        $exception = $this->captureValidationException(fn () => $service->reopen($actor, $operation->fresh()));

        $this->assertSame(
            'This settlement cannot be reopened because one of the receiving ledgers already started moving funds after the payout landed. Reopening now could break later transfer history.',
            $exception->errors()['settlement'][0] ?? null,
        );
    }

    public function test_reopen_rejects_settlements_after_downstream_org_funds_transfer_activity(): void
    {
        $actor = $this->member();
        $operation = $this->completedOperation();
        $settlement = OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
            'finalized_by_user_id' => $actor->id,
            'finalized_at' => now()->subHour(),
        ]);
        $wipe = $this->currentWipeCycle();
        $account = app(LedgerCycleService::class)->defaultOrgAccountFor($actor);

        LedgerTransaction::query()->create([
            'user_id' => $actor->id,
            'squadron_id' => null,
            'is_org_owned' => true,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 8000,
            'currency' => 'aUEC',
            'source_type' => 'operation_settlement',
            'description' => 'Org settlement payout',
            'transaction_date' => now()->subHour(),
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'provenance_locked' => true,
        ]);

        LedgerTransferRequest::query()->create([
            'transfer_kind' => 'funds',
            'status' => 'pending',
            'requested_by_user_id' => $actor->id,
            'wipe_cycle_id' => $wipe->id,
            'source_user_id' => null,
            'source_squadron_id' => null,
            'source_is_org_owned' => true,
            'destination_user_id' => $actor->id,
            'destination_squadron_id' => null,
            'destination_is_org_owned' => false,
            'destination_is_external' => false,
            'amount' => 2500,
            'currency' => 'aUEC',
            'description' => 'Move org payout onward',
            'transaction_date' => now()->subMinutes(30),
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        $service = new OperationSettlementService(Mockery::mock(LedgerService::class));

        $exception = $this->captureValidationException(fn () => $service->reopen($actor, $operation->fresh()));

        $this->assertSame(
            'This settlement cannot be reopened because one of the receiving ledgers already started moving funds after the payout landed. Reopening now could break later transfer history.',
            $exception->errors()['settlement'][0] ?? null,
        );
    }

    public function test_reopen_clears_finalization_and_deletes_receipts_when_safe(): void
    {
        $actor = $this->member();
        $recipient = $this->member();
        $operation = $this->completedOperation();
        $settlement = OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
            'finalized_by_user_id' => $actor->id,
            'finalized_at' => now()->subHour(),
        ]);
        $wipe = $this->currentWipeCycle();
        $account = app(LedgerCycleService::class)->defaultAccountFor($recipient);

        $transaction = LedgerTransaction::query()->create([
            'user_id' => $recipient->id,
            'squadron_id' => null,
            'is_org_owned' => false,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 6000,
            'currency' => 'aUEC',
            'source_type' => 'operation_settlement',
            'description' => 'Settlement payout',
            'transaction_date' => now()->subHour(),
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'provenance_locked' => true,
        ]);

        $item = LedgerInventoryItem::query()->create([
            'user_id' => $recipient->id,
            'squadron_id' => null,
            'is_org_owned' => false,
            'wipe_cycle_id' => $wipe->id,
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'source_type' => 'commodity',
            'uex_reference_type' => 'commodity',
            'uex_reference_id' => 9001,
            'category' => 'Commodity',
            'quantity' => 8,
            'unit_label' => 'SCU',
            'currency' => 'aUEC',
            'status' => 'stored',
            'provenance_locked' => true,
            'acquired_at' => now()->subHour(),
        ]);

        $service = new OperationSettlementService(Mockery::mock(LedgerService::class));
        $reopened = $service->reopen($actor, $operation->fresh());

        $this->assertNull($reopened->finalized_at);
        $this->assertNull($reopened->finalized_by_user_id);
        $this->assertSame($actor->id, $reopened->reopened_by_user_id);
        $this->assertNotNull($reopened->reopened_at);
        $this->assertDatabaseMissing('ledger_transactions', ['id' => $transaction->id]);
        $this->assertDatabaseMissing('ledger_inventory_items', ['id' => $item->id]);
    }

    public function test_reopen_rejects_mixed_settlement_when_any_receiving_ledger_has_downstream_transfer_activity(): void
    {
        $actor = $this->member();
        $personalRecipient = $this->member();
        $squadron = Squadron::query()->create([
            'name' => 'Aurora Wing',
            'slug' => 'aurora-wing',
            'status' => 'active',
            'leader_id' => $actor->id,
        ]);
        $operation = $this->completedOperation([
            'squadron_id' => $squadron->id,
            'squadron_name' => $squadron->name,
        ]);
        $settlement = OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
            'finalized_by_user_id' => $actor->id,
            'finalized_at' => now()->subHour(),
        ]);
        $wipe = $this->currentWipeCycle();

        $personalAccount = app(LedgerCycleService::class)->defaultAccountFor($personalRecipient);
        $squadronAccount = app(LedgerCycleService::class)->defaultSquadronAccountFor($squadron, $actor);
        $orgAccount = app(LedgerCycleService::class)->defaultOrgAccountFor($actor);

        LedgerTransaction::query()->create([
            'user_id' => $personalRecipient->id,
            'squadron_id' => null,
            'is_org_owned' => false,
            'ledger_account_id' => $personalAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 2000,
            'currency' => 'aUEC',
            'source_type' => 'operation_settlement',
            'description' => 'Personal payout',
            'transaction_date' => now()->subHour(),
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'provenance_locked' => true,
        ]);

        LedgerTransaction::query()->create([
            'user_id' => $actor->id,
            'squadron_id' => $squadron->id,
            'is_org_owned' => false,
            'ledger_account_id' => $squadronAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 3000,
            'currency' => 'aUEC',
            'source_type' => 'operation_settlement',
            'description' => 'Squadron payout',
            'transaction_date' => now()->subHour(),
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'provenance_locked' => true,
        ]);

        LedgerTransaction::query()->create([
            'user_id' => $actor->id,
            'squadron_id' => null,
            'is_org_owned' => true,
            'ledger_account_id' => $orgAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 4000,
            'currency' => 'aUEC',
            'source_type' => 'operation_settlement',
            'description' => 'Org payout',
            'transaction_date' => now()->subHour(),
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'provenance_locked' => true,
        ]);

        LedgerTransferRequest::query()->create([
            'transfer_kind' => 'funds',
            'status' => 'approved',
            'requested_by_user_id' => $actor->id,
            'wipe_cycle_id' => $wipe->id,
            'source_user_id' => null,
            'source_squadron_id' => $squadron->id,
            'source_is_org_owned' => false,
            'destination_user_id' => null,
            'destination_squadron_id' => null,
            'destination_is_org_owned' => true,
            'destination_is_external' => false,
            'amount' => 500,
            'currency' => 'aUEC',
            'description' => 'Mixed settlement downstream move',
            'transaction_date' => now()->subMinutes(30),
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        $service = new OperationSettlementService(Mockery::mock(LedgerService::class));

        $exception = $this->captureValidationException(fn () => $service->reopen($actor, $operation->fresh()));

        $this->assertSame(
            'This settlement cannot be reopened because one of the receiving ledgers already started moving funds after the payout landed. Reopening now could break later transfer history.',
            $exception->errors()['settlement'][0] ?? null,
        );
    }

    public function test_reopen_rejects_mixed_settlement_when_any_generated_loot_receipt_has_transfer_descendants(): void
    {
        $actor = $this->member();
        $personalRecipient = $this->member();
        $squadron = Squadron::query()->create([
            'name' => 'Aurora Wing',
            'slug' => 'aurora-wing-descendants',
            'status' => 'active',
            'leader_id' => $actor->id,
        ]);
        $operation = $this->completedOperation([
            'squadron_id' => $squadron->id,
            'squadron_name' => $squadron->name,
        ]);
        $settlement = OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
            'finalized_by_user_id' => $actor->id,
            'finalized_at' => now()->subHour(),
        ]);
        $wipe = $this->currentWipeCycle();

        LedgerInventoryItem::query()->create([
            'user_id' => $personalRecipient->id,
            'squadron_id' => null,
            'is_org_owned' => false,
            'wipe_cycle_id' => $wipe->id,
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'source_type' => 'commodity',
            'uex_reference_type' => 'commodity',
            'uex_reference_id' => 9001,
            'category' => 'Commodity',
            'quantity' => 4,
            'unit_label' => 'SCU',
            'currency' => 'aUEC',
            'status' => 'stored',
            'provenance_locked' => true,
            'acquired_at' => now()->subHour(),
        ]);

        $squadronItem = LedgerInventoryItem::query()->create([
            'user_id' => $actor->id,
            'squadron_id' => $squadron->id,
            'is_org_owned' => false,
            'wipe_cycle_id' => $wipe->id,
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'source_type' => 'item',
            'uex_reference_type' => 'item',
            'uex_reference_id' => 9101,
            'category' => 'Item',
            'quantity' => 1,
            'unit_label' => 'units',
            'currency' => 'aUEC',
            'status' => 'stored',
            'provenance_locked' => true,
            'acquired_at' => now()->subHour(),
        ]);

        LedgerInventoryItem::query()->create([
            'user_id' => $actor->id,
            'squadron_id' => null,
            'is_org_owned' => true,
            'wipe_cycle_id' => $wipe->id,
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'source_type' => 'component',
            'uex_reference_type' => 'item',
            'uex_reference_id' => 9101,
            'category' => 'Component',
            'quantity' => 2,
            'unit_label' => 'units',
            'currency' => 'aUEC',
            'status' => 'stored',
            'provenance_locked' => true,
            'acquired_at' => now()->subHour(),
        ]);

        LedgerInventoryItem::query()->create([
            'user_id' => $actor->id,
            'squadron_id' => $squadron->id,
            'is_org_owned' => false,
            'wipe_cycle_id' => $wipe->id,
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => null,
            'transfer_origin_item_id' => $squadronItem->id,
            'source_type' => 'item',
            'uex_reference_type' => 'item',
            'uex_reference_id' => 9101,
            'category' => 'Item',
            'quantity' => 1,
            'unit_label' => 'units',
            'currency' => 'aUEC',
            'status' => 'stored',
            'provenance_locked' => true,
            'acquired_at' => now()->subMinutes(30),
        ]);

        $service = new OperationSettlementService(Mockery::mock(LedgerService::class));

        $exception = $this->captureValidationException(fn () => $service->reopen($actor, $operation->fresh()));

        $this->assertSame(
            'This settlement cannot be reopened because some of its loot receipts have already been moved into other ledgers. Those downstream inventory moves need to stay intact.',
            $exception->errors()['settlement'][0] ?? null,
        );
    }

    public function test_reopen_clears_all_generated_receipts_for_mixed_personal_squadron_and_org_settlement_when_safe(): void
    {
        $actor = $this->member();
        $personalRecipient = $this->member();
        $squadron = Squadron::query()->create([
            'name' => 'Aurora Wing',
            'slug' => 'aurora-wing-safe',
            'status' => 'active',
            'leader_id' => $actor->id,
        ]);
        $operation = $this->completedOperation([
            'squadron_id' => $squadron->id,
            'squadron_name' => $squadron->name,
        ]);
        $settlement = OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
            'finalized_by_user_id' => $actor->id,
            'finalized_at' => now()->subHour(),
        ]);
        $wipe = $this->currentWipeCycle();

        $personalAccount = app(LedgerCycleService::class)->defaultAccountFor($personalRecipient);
        $squadronAccount = app(LedgerCycleService::class)->defaultSquadronAccountFor($squadron, $actor);
        $orgAccount = app(LedgerCycleService::class)->defaultOrgAccountFor($actor);

        $personalTransaction = LedgerTransaction::query()->create([
            'user_id' => $personalRecipient->id,
            'squadron_id' => null,
            'is_org_owned' => false,
            'ledger_account_id' => $personalAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 2000,
            'currency' => 'aUEC',
            'source_type' => 'operation_settlement',
            'description' => 'Personal payout',
            'transaction_date' => now()->subHour(),
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'provenance_locked' => true,
        ]);

        $squadronTransaction = LedgerTransaction::query()->create([
            'user_id' => $actor->id,
            'squadron_id' => $squadron->id,
            'is_org_owned' => false,
            'ledger_account_id' => $squadronAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 3000,
            'currency' => 'aUEC',
            'source_type' => 'operation_settlement',
            'description' => 'Squadron payout',
            'transaction_date' => now()->subHour(),
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'provenance_locked' => true,
        ]);

        $orgTransaction = LedgerTransaction::query()->create([
            'user_id' => $actor->id,
            'squadron_id' => null,
            'is_org_owned' => true,
            'ledger_account_id' => $orgAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 4000,
            'currency' => 'aUEC',
            'source_type' => 'operation_settlement',
            'description' => 'Org payout',
            'transaction_date' => now()->subHour(),
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'provenance_locked' => true,
        ]);

        $personalItem = LedgerInventoryItem::query()->create([
            'user_id' => $personalRecipient->id,
            'squadron_id' => null,
            'is_org_owned' => false,
            'wipe_cycle_id' => $wipe->id,
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'source_type' => 'commodity',
            'uex_reference_type' => 'commodity',
            'uex_reference_id' => 9001,
            'category' => 'Commodity',
            'quantity' => 4,
            'unit_label' => 'SCU',
            'currency' => 'aUEC',
            'status' => 'stored',
            'provenance_locked' => true,
            'acquired_at' => now()->subHour(),
        ]);

        $squadronItem = LedgerInventoryItem::query()->create([
            'user_id' => $actor->id,
            'squadron_id' => $squadron->id,
            'is_org_owned' => false,
            'wipe_cycle_id' => $wipe->id,
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'source_type' => 'item',
            'uex_reference_type' => 'item',
            'uex_reference_id' => 9101,
            'category' => 'Item',
            'quantity' => 1,
            'unit_label' => 'units',
            'currency' => 'aUEC',
            'status' => 'stored',
            'provenance_locked' => true,
            'acquired_at' => now()->subHour(),
        ]);

        $orgItem = LedgerInventoryItem::query()->create([
            'user_id' => $actor->id,
            'squadron_id' => null,
            'is_org_owned' => true,
            'wipe_cycle_id' => $wipe->id,
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'source_type' => 'component',
            'uex_reference_type' => 'item',
            'uex_reference_id' => 9101,
            'category' => 'Component',
            'quantity' => 2,
            'unit_label' => 'units',
            'currency' => 'aUEC',
            'status' => 'stored',
            'provenance_locked' => true,
            'acquired_at' => now()->subHour(),
        ]);

        $service = new OperationSettlementService(Mockery::mock(LedgerService::class));
        $reopened = $service->reopen($actor, $operation->fresh());

        $this->assertNull($reopened->finalized_at);
        $this->assertNull($reopened->finalized_by_user_id);
        $this->assertSame($actor->id, $reopened->reopened_by_user_id);
        $this->assertNotNull($reopened->reopened_at);
        $this->assertDatabaseMissing('ledger_transactions', ['id' => $personalTransaction->id]);
        $this->assertDatabaseMissing('ledger_transactions', ['id' => $squadronTransaction->id]);
        $this->assertDatabaseMissing('ledger_transactions', ['id' => $orgTransaction->id]);
        $this->assertDatabaseMissing('ledger_inventory_items', ['id' => $personalItem->id]);
        $this->assertDatabaseMissing('ledger_inventory_items', ['id' => $squadronItem->id]);
        $this->assertDatabaseMissing('ledger_inventory_items', ['id' => $orgItem->id]);
    }

    public function test_finalize_routes_money_receipts_to_member_squadron_and_organization_ledgers(): void
    {
        $actor = $this->member(['rsi_handle' => 'SettlementActor']);
        $memberRecipient = $this->member(['rsi_handle' => 'MemberRecipient']);
        $squadron = Squadron::query()->create([
            'name' => 'Nova Wing',
            'slug' => 'nova-wing',
            'status' => 'active',
            'leader_id' => $actor->id,
        ]);
        $operation = $this->completedOperation([
            'squadron_id' => $squadron->id,
            'squadron_name' => $squadron->name,
            'after_action_attendance_user_ids' => [$memberRecipient->id],
        ]);

        $this->currentWipeCycle();

        $ledger = Mockery::mock(LedgerService::class);
        $ledger->shouldReceive('createTransaction')
            ->once()
            ->with(
                Mockery::on(fn ($user) => $user instanceof User && $user->is($actor)),
                Mockery::on(fn ($user) => $user instanceof User && $user->is($memberRecipient)),
                Mockery::on(function (array $payload) use ($operation) {
                    return $payload['amount'] === 4200
                        && $payload['type'] === 'income'
                        && $payload['source_type'] === 'operation_settlement'
                        && $payload['related_operation_id'] === $operation->id
                        && is_int($payload['operation_settlement_id'] ?? null)
                        && ($payload['operation_settlement_id'] ?? 0) > 0
                        && $payload['currency'] === 'aUEC'
                        && $payload['provenance_locked'] === true
                        && ($payload['transaction_date'] ?? null) instanceof \Illuminate\Support\Carbon
                        && $payload['transaction_date']->eq($operation->ends_at)
                        && str_contains((string) ($payload['description'] ?? ''), $operation->title)
                        && ($payload['notes'] ?? null) === 'Crew share';
                })
            );
        $ledger->shouldReceive('createSquadronTransaction')
            ->once()
            ->with(
                Mockery::on(fn ($user) => $user instanceof User && $user->is($actor)),
                Mockery::on(fn ($target) => $target instanceof Squadron && $target->is($squadron)),
                Mockery::on(function (array $payload) use ($operation) {
                    return $payload['amount'] === 1800
                        && $payload['type'] === 'income'
                        && $payload['source_type'] === 'operation_settlement'
                        && $payload['related_operation_id'] === $operation->id
                        && is_int($payload['operation_settlement_id'] ?? null)
                        && $payload['provenance_locked'] === true
                        && ($payload['transaction_date'] ?? null) instanceof \Illuminate\Support\Carbon
                        && $payload['transaction_date']->eq($operation->ends_at)
                        && ($payload['notes'] ?? null) === 'Wing reserve';
                })
            );
        $ledger->shouldReceive('createOrgTransaction')
            ->once()
            ->with(
                Mockery::on(fn ($user) => $user instanceof User && $user->is($actor)),
                Mockery::on(function (array $payload) use ($operation) {
                    return $payload['amount'] === 900
                        && $payload['type'] === 'income'
                        && $payload['source_type'] === 'operation_settlement'
                        && $payload['related_operation_id'] === $operation->id
                        && is_int($payload['operation_settlement_id'] ?? null)
                        && $payload['provenance_locked'] === true
                        && ($payload['transaction_date'] ?? null) instanceof \Illuminate\Support\Carbon
                        && $payload['transaction_date']->eq($operation->ends_at)
                        && ($payload['notes'] ?? null) === 'Horizon reserve';
                })
            );

        $service = new OperationSettlementService($ledger);
        $settlement = $service->finalize($actor, $operation, [
            'money_rows' => [
                [
                    'row_key' => 'member-pay',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $memberRecipient->id,
                    'amount' => 4200,
                    'notes' => 'Crew share',
                ],
                [
                    'row_key' => 'squadron-pay',
                    'recipient_type' => 'squadron',
                    'recipient_squadron_id' => $squadron->id,
                    'amount' => 1800,
                    'notes' => 'Wing reserve',
                ],
                [
                    'row_key' => 'org-pay',
                    'recipient_type' => 'organization',
                    'amount' => 900,
                    'notes' => 'Horizon reserve',
                ],
            ],
            'loot_rows' => [],
        ]);

        $this->assertNotNull($settlement->finalized_at);
        $this->assertSame($actor->id, $settlement->finalized_by_user_id);
    }

    public function test_finalize_routes_loot_receipts_to_member_squadron_and_organization_ledgers(): void
    {
        $actor = $this->member(['rsi_handle' => 'LootActor']);
        $memberRecipient = $this->member(['rsi_handle' => 'LootRecipient']);
        $squadron = Squadron::query()->create([
            'name' => 'Polaris Wing',
            'slug' => 'polaris-wing',
            'status' => 'active',
            'leader_id' => $actor->id,
        ]);
        $operation = $this->completedOperation([
            'squadron_id' => $squadron->id,
            'squadron_name' => $squadron->name,
            'after_action_attendance_user_ids' => [$memberRecipient->id],
        ]);

        $this->currentWipeCycle();
        $this->seedUexSettlementReferences();

        $ledger = Mockery::mock(LedgerService::class);
        $ledger->shouldReceive('createInventoryItem')
            ->once()
            ->with(
                Mockery::on(fn ($user) => $user instanceof User && $user->is($actor)),
                Mockery::on(fn ($user) => $user instanceof User && $user->is($memberRecipient)),
                Mockery::on(function (array $payload) use ($operation) {
                    return $payload['source_type'] === 'item'
                        && $payload['uex_reference_type'] === 'item'
                        && $payload['uex_reference_id'] === 9101
                        && $payload['category'] === 'Item'
                        && $payload['quantity'] === 1
                        && $payload['unit_label'] === 'units'
                        && $payload['status'] === 'stored'
                        && $payload['location_name'] === $operation->title
                        && $payload['related_operation_id'] === $operation->id
                        && is_int($payload['operation_settlement_id'] ?? null)
                        && ($payload['operation_settlement_id'] ?? 0) > 0
                        && $payload['provenance_locked'] === true
                        && ($payload['acquired_at'] ?? null) instanceof \Illuminate\Support\Carbon
                        && $payload['acquired_at']->eq($operation->ends_at)
                        && ($payload['notes'] ?? null) === 'Ship component';
                })
            );
        $ledger->shouldReceive('createSquadronInventoryItem')
            ->once()
            ->with(
                Mockery::on(fn ($user) => $user instanceof User && $user->is($actor)),
                Mockery::on(fn ($target) => $target instanceof Squadron && $target->is($squadron)),
                Mockery::on(function (array $payload) use ($operation) {
                    return $payload['source_type'] === 'commodity'
                        && $payload['uex_reference_type'] === 'commodity'
                        && $payload['uex_reference_id'] === 9001
                        && $payload['category'] === 'Commodity'
                        && $payload['quantity'] === 6
                        && $payload['unit_label'] === 'SCU'
                        && $payload['status'] === 'stored'
                        && $payload['location_name'] === $operation->title
                        && $payload['related_operation_id'] === $operation->id
                        && is_int($payload['operation_settlement_id'] ?? null)
                        && $payload['provenance_locked'] === true
                        && ($payload['acquired_at'] ?? null) instanceof \Illuminate\Support\Carbon
                        && $payload['acquired_at']->eq($operation->ends_at)
                        && ($payload['notes'] ?? null) === 'Cargo reserve';
                })
            );
        $ledger->shouldReceive('createOrgInventoryItem')
            ->once()
            ->with(
                Mockery::on(fn ($user) => $user instanceof User && $user->is($actor)),
                Mockery::on(function (array $payload) use ($operation) {
                    return $payload['source_type'] === 'component'
                        && $payload['uex_reference_type'] === 'item'
                        && $payload['uex_reference_id'] === 9101
                        && $payload['category'] === 'Component'
                        && $payload['quantity'] === 2
                        && $payload['unit_label'] === 'units'
                        && $payload['status'] === 'stored'
                        && $payload['location_name'] === $operation->title
                        && $payload['related_operation_id'] === $operation->id
                        && is_int($payload['operation_settlement_id'] ?? null)
                        && $payload['provenance_locked'] === true
                        && ($payload['acquired_at'] ?? null) instanceof \Illuminate\Support\Carbon
                        && $payload['acquired_at']->eq($operation->ends_at)
                        && ($payload['notes'] ?? null) === 'Org stash';
                })
            );

        $service = new OperationSettlementService($ledger);
        $settlement = $service->finalize($actor, $operation, [
            'money_rows' => [],
            'loot_rows' => [
                [
                    'row_key' => 'member-loot',
                    'source_type' => 'item',
                    'uex_reference_id' => 9101,
                    'recipient_type' => 'member',
                    'recipient_user_id' => $memberRecipient->id,
                    'quantity' => 1,
                    'unit_label' => '',
                    'notes' => 'Ship component',
                ],
                [
                    'row_key' => 'squadron-loot',
                    'source_type' => 'commodity',
                    'uex_reference_id' => 9001,
                    'recipient_type' => 'squadron',
                    'recipient_squadron_id' => $squadron->id,
                    'quantity' => 6,
                    'unit_label' => '',
                    'notes' => 'Cargo reserve',
                ],
                [
                    'row_key' => 'org-loot',
                    'source_type' => 'component',
                    'uex_reference_id' => 9101,
                    'recipient_type' => 'organization',
                    'quantity' => 2,
                    'unit_label' => '',
                    'notes' => 'Org stash',
                ],
            ],
        ]);

        $this->assertNotNull($settlement->finalized_at);
        $this->assertSame($actor->id, $settlement->finalized_by_user_id);
    }

    private function completedOperation(array $attributes = []): Operation
    {
        $operation = new Operation();
        $operation->forceFill(array_merge([
            'created_by' => $this->member()->id,
            'title' => 'Settlement Service Test',
            'description' => 'Completed operation for settlement service tests.',
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
            'status' => OperationStatus::Completed->value,
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

    private function currentWipeCycle(): WipeCycle
    {
        return WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDay(),
            'is_current' => true,
        ]);
    }

    private function seedUexSettlementReferences(): void
    {
        DB::table('uex_commodities')->insert([
            'uex_id' => 9001,
            'name' => 'Agricium',
            'source_payload' => json_encode(['name' => 'Agricium']),
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('uex_items')->insert([
            'uex_id' => 9101,
            'name' => 'Atlas Drive',
            'type' => 'Quantum Drive',
            'source_payload' => json_encode(['name' => 'Atlas Drive']),
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
