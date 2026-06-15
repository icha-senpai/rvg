<?php

namespace Tests\Unit;

use App\Models\LedgerTransaction;
use App\Models\Operation;
use App\Models\OperationSettlement;
use App\Models\User;
use App\Models\WipeCycle;
use App\Services\LedgerCycleService;
use App\Services\LedgerWriteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LedgerWriteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_transaction_rejects_zero_adjustment_amount(): void
    {
        $service = app(LedgerWriteService::class);
        $actor = User::factory()->create();
        $owner = User::factory()->create();

        $exception = $this->captureValidationException(fn () => $service->createTransaction($actor, $owner, [
            'type' => 'adjustment',
            'amount' => 0,
            'description' => 'Zero adjustment should fail',
        ]));

        $this->assertSame('Adjustment amount cannot be zero.', $exception->errors()['amount'][0] ?? null);
    }

    public function test_create_inventory_item_requires_reference_for_non_custom_records(): void
    {
        $service = app(LedgerWriteService::class);
        $actor = User::factory()->create();
        $owner = User::factory()->create();

        $exception = $this->captureValidationException(fn () => $service->createInventoryItem($actor, $owner, [
            'source_type' => 'item',
            'quantity' => 1,
        ]));

        $this->assertSame(
            'Pick a cached UEX reference or switch this inventory record to custom.',
            $exception->errors()['uex_reference_id'][0] ?? null,
        );
    }

    public function test_create_inventory_item_requires_name_for_custom_records(): void
    {
        $service = app(LedgerWriteService::class);
        $actor = User::factory()->create();
        $owner = User::factory()->create();

        $exception = $this->captureValidationException(fn () => $service->createInventoryItem($actor, $owner, [
            'source_type' => 'custom',
            'quantity' => 1,
        ]));

        $this->assertSame(
            'Custom inventory records need a name.',
            $exception->errors()['custom_name'][0] ?? null,
        );
    }

    public function test_create_org_inventory_item_preserves_settlement_provenance_fields(): void
    {
        $service = app(LedgerWriteService::class);
        $actor = User::factory()->create();
        $operation = Operation::query()->create([
            'created_by' => $actor->id,
            'title' => 'Org Settlement Source',
            'description' => 'Operation used for ledger provenance testing.',
            'status' => 'completed',
            'visibility' => 'open',
            'starts_at' => now()->subHour(),
            'ends_at' => now(),
        ]);
        $settlement = OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
        ]);

        $item = $service->createOrgInventoryItem($actor, [
            'source_type' => 'component',
            'uex_reference_type' => 'item',
            'uex_reference_id' => 9101,
            'category' => 'Component',
            'quantity' => 2,
            'unit_label' => 'units',
            'status' => 'stored',
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'provenance_locked' => true,
        ]);

        $this->assertTrue((bool) $item->is_org_owned);
        $this->assertSame($operation->id, $item->related_operation_id);
        $this->assertSame($settlement->id, $item->operation_settlement_id);
        $this->assertTrue((bool) $item->provenance_locked);
    }

    public function test_update_transaction_rejects_provenance_locked_records(): void
    {
        $service = app(LedgerWriteService::class);
        $actor = User::factory()->create();
        $owner = User::factory()->create();

        $transaction = $service->createTransaction($actor, $owner, [
            'type' => 'income',
            'amount' => 2500,
            'description' => 'Locked settlement-like row',
            'provenance_locked' => true,
            'source_type' => 'operation_settlement',
        ]);

        $exception = $this->captureValidationException(fn () => $service->updateTransaction($actor, $owner, $transaction, [
            'type' => 'income',
            'amount' => 3000,
            'description' => 'Should stay locked',
        ]));

        $this->assertSame(
            'Settlement and transfer records stay locked so their audit trail stays intact. Create a new correcting entry or reopen the source workflow instead.',
            $exception->errors()['transaction'][0] ?? null,
        );
    }

    public function test_delete_transaction_rejects_transfer_rows_even_when_not_provenance_locked(): void
    {
        $cycles = app(LedgerCycleService::class);
        $service = app(LedgerWriteService::class);
        $actor = User::factory()->create();
        $owner = User::factory()->create();
        $wipe = $this->currentWipeCycle();
        $account = $cycles->defaultAccountFor($owner);

        $transaction = LedgerTransaction::query()->create([
            'user_id' => $owner->id,
            'squadron_id' => null,
            'is_org_owned' => false,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'expense',
            'amount' => 1200,
            'currency' => 'aUEC',
            'source_type' => 'transfer',
            'description' => 'Transfer-created row',
            'transaction_date' => now(),
            'provenance_locked' => false,
        ]);

        $exception = $this->captureValidationException(fn () => $service->deleteTransaction($actor, $owner, $transaction));

        $this->assertSame(
            'Transfer records stay locked so the paired ledgers stay in sync. Create a new transfer or reversal instead.',
            $exception->errors()['transaction'][0] ?? null,
        );
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
