<?php

namespace App\Services;

use App\Models\LedgerActivityLog;
use App\Models\LedgerInventoryItem;
use App\Models\LedgerShipAsset;
use App\Models\LedgerTrade;
use App\Models\LedgerTransaction;
use App\Models\Squadron;
use App\Models\User;
use App\Models\WipeCycle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LedgerWriteService
{
    public function __construct(
        protected LedgerCycleService $cycles,
    ) {}

    public function createTransaction(User $actor, User $owner, array $data): LedgerTransaction
    {
        return $this->createTransactionForOwner($actor, $this->personalOwner($owner), $data);
    }

    public function createTrade(User $actor, User $owner, array $data): LedgerTrade
    {
        return $this->createTradeForOwner($actor, $this->personalOwner($owner), $data);
    }

    public function createInventoryItem(User $actor, User $owner, array $data): LedgerInventoryItem
    {
        return $this->createInventoryItemForOwner($actor, $this->personalOwner($owner), $data);
    }

    public function createShipAsset(User $actor, User $owner, array $data): LedgerShipAsset
    {
        return $this->createShipAssetForOwner($actor, $this->personalOwner($owner), $data);
    }

    public function updateTransaction(User $actor, User $owner, LedgerTransaction $transaction, array $data): LedgerTransaction
    {
        return $this->updateTransactionForOwner($actor, $this->personalOwner($owner), $transaction, $data);
    }

    public function deleteTransaction(User $actor, User $owner, LedgerTransaction $transaction): void
    {
        $this->deleteTransactionForOwner($actor, $this->personalOwner($owner), $transaction);
    }

    public function updateTrade(User $actor, User $owner, LedgerTrade $trade, array $data): LedgerTrade
    {
        return $this->updateTradeForOwner($actor, $this->personalOwner($owner), $trade, $data);
    }

    public function deleteTrade(User $actor, User $owner, LedgerTrade $trade): void
    {
        $this->deleteTradeForOwner($actor, $this->personalOwner($owner), $trade);
    }

    public function updateInventoryItem(User $actor, User $owner, LedgerInventoryItem $item, array $data): LedgerInventoryItem
    {
        return $this->updateInventoryItemForOwner($actor, $this->personalOwner($owner), $item, $data);
    }

    public function deleteInventoryItem(User $actor, User $owner, LedgerInventoryItem $item): void
    {
        $this->deleteInventoryItemForOwner($actor, $this->personalOwner($owner), $item);
    }

    public function updateShipAsset(User $actor, User $owner, LedgerShipAsset $asset, array $data): LedgerShipAsset
    {
        return $this->updateShipAssetForOwner($actor, $this->personalOwner($owner), $asset, $data);
    }

    public function deleteShipAsset(User $actor, User $owner, LedgerShipAsset $asset): void
    {
        $this->deleteShipAssetForOwner($actor, $this->personalOwner($owner), $asset);
    }

    public function createSquadronTransaction(User $actor, Squadron $squadron, array $data): LedgerTransaction
    {
        return $this->createTransactionForOwner($actor, $this->squadronOwner($actor, $squadron), $data);
    }

    public function updateSquadronTransaction(User $actor, Squadron $squadron, LedgerTransaction $transaction, array $data): LedgerTransaction
    {
        return $this->updateTransactionForOwner($actor, $this->squadronOwner($actor, $squadron), $transaction, $data);
    }

    public function deleteSquadronTransaction(User $actor, Squadron $squadron, LedgerTransaction $transaction): void
    {
        $this->deleteTransactionForOwner($actor, $this->squadronOwner($actor, $squadron), $transaction);
    }

    public function createSquadronTrade(User $actor, Squadron $squadron, array $data): LedgerTrade
    {
        return $this->createTradeForOwner($actor, $this->squadronOwner($actor, $squadron), $data);
    }

    public function updateSquadronTrade(User $actor, Squadron $squadron, LedgerTrade $trade, array $data): LedgerTrade
    {
        return $this->updateTradeForOwner($actor, $this->squadronOwner($actor, $squadron), $trade, $data);
    }

    public function deleteSquadronTrade(User $actor, Squadron $squadron, LedgerTrade $trade): void
    {
        $this->deleteTradeForOwner($actor, $this->squadronOwner($actor, $squadron), $trade);
    }

    public function createSquadronInventoryItem(User $actor, Squadron $squadron, array $data): LedgerInventoryItem
    {
        return $this->createInventoryItemForOwner($actor, $this->squadronOwner($actor, $squadron), $data);
    }

    public function updateSquadronInventoryItem(User $actor, Squadron $squadron, LedgerInventoryItem $item, array $data): LedgerInventoryItem
    {
        return $this->updateInventoryItemForOwner($actor, $this->squadronOwner($actor, $squadron), $item, $data);
    }

    public function deleteSquadronInventoryItem(User $actor, Squadron $squadron, LedgerInventoryItem $item): void
    {
        $this->deleteInventoryItemForOwner($actor, $this->squadronOwner($actor, $squadron), $item);
    }

    public function createSquadronShipAsset(User $actor, Squadron $squadron, array $data): LedgerShipAsset
    {
        return $this->createShipAssetForOwner($actor, $this->squadronOwner($actor, $squadron), $data);
    }

    public function updateSquadronShipAsset(User $actor, Squadron $squadron, LedgerShipAsset $asset, array $data): LedgerShipAsset
    {
        return $this->updateShipAssetForOwner($actor, $this->squadronOwner($actor, $squadron), $asset, $data);
    }

    public function deleteSquadronShipAsset(User $actor, Squadron $squadron, LedgerShipAsset $asset): void
    {
        $this->deleteShipAssetForOwner($actor, $this->squadronOwner($actor, $squadron), $asset);
    }

    public function createOrgTransaction(User $actor, array $data): LedgerTransaction
    {
        return $this->createTransactionForOwner($actor, $this->orgOwner($actor), $data);
    }

    public function updateOrgTransaction(User $actor, LedgerTransaction $transaction, array $data): LedgerTransaction
    {
        return $this->updateTransactionForOwner($actor, $this->orgOwner($actor), $transaction, $data);
    }

    public function deleteOrgTransaction(User $actor, LedgerTransaction $transaction): void
    {
        $this->deleteTransactionForOwner($actor, $this->orgOwner($actor), $transaction);
    }

    public function createOrgTrade(User $actor, array $data): LedgerTrade
    {
        return $this->createTradeForOwner($actor, $this->orgOwner($actor), $data);
    }

    public function updateOrgTrade(User $actor, LedgerTrade $trade, array $data): LedgerTrade
    {
        return $this->updateTradeForOwner($actor, $this->orgOwner($actor), $trade, $data);
    }

    public function deleteOrgTrade(User $actor, LedgerTrade $trade): void
    {
        $this->deleteTradeForOwner($actor, $this->orgOwner($actor), $trade);
    }

    public function createOrgInventoryItem(User $actor, array $data): LedgerInventoryItem
    {
        return $this->createInventoryItemForOwner($actor, $this->orgOwner($actor), $data);
    }

    public function updateOrgInventoryItem(User $actor, LedgerInventoryItem $item, array $data): LedgerInventoryItem
    {
        return $this->updateInventoryItemForOwner($actor, $this->orgOwner($actor), $item, $data);
    }

    public function deleteOrgInventoryItem(User $actor, LedgerInventoryItem $item): void
    {
        $this->deleteInventoryItemForOwner($actor, $this->orgOwner($actor), $item);
    }

    public function createOrgShipAsset(User $actor, array $data): LedgerShipAsset
    {
        return $this->createShipAssetForOwner($actor, $this->orgOwner($actor), $data);
    }

    public function updateOrgShipAsset(User $actor, LedgerShipAsset $asset, array $data): LedgerShipAsset
    {
        return $this->updateShipAssetForOwner($actor, $this->orgOwner($actor), $asset, $data);
    }

    public function deleteOrgShipAsset(User $actor, LedgerShipAsset $asset): void
    {
        $this->deleteShipAssetForOwner($actor, $this->orgOwner($actor), $asset);
    }

    protected function createTransactionForOwner(User $actor, LedgerOwner $owner, array $data): LedgerTransaction
    {
        return DB::transaction(function () use ($actor, $owner, $data) {
            $account = $owner->resolveAccount($data['ledger_account_id'] ?? null);
            $wipeCycle = $this->cycles->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $amount = $this->wholeNumber($data['amount']);

            $this->assertTransactionAmountIsValid($data['type'], $amount, $owner instanceof PersonalLedgerOwner);

            $transaction = LedgerTransaction::query()->create($owner->applyOwnership([
                'ledger_account_id' => $account->id,
                'wipe_cycle_id' => $wipeCycle->id,
                'type' => $data['type'],
                'amount' => $amount,
                'currency' => $data['currency'] ?? $account->currency ?? 'aUEC',
                'source_type' => $data['source_type'] ?? null,
                'description' => $data['description'],
                'transaction_date' => $data['transaction_date'] ?? now(),
                'related_ship_asset_id' => $owner->resolveShipAssetId($data['related_ship_asset_id'] ?? null),
                'related_operation_id' => $data['related_operation_id'] ?? null,
                'operation_settlement_id' => $data['operation_settlement_id'] ?? null,
                'related_uex_type' => $data['related_uex_type'] ?? null,
                'related_uex_id' => $data['related_uex_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'provenance_locked' => (bool) ($data['provenance_locked'] ?? false),
            ]));

            $this->logActivity($actor, $owner, $wipeCycle, 'transaction.created', $transaction, [
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'description' => $transaction->description,
            ]);

            return $transaction;
        });
    }

    protected function updateTransactionForOwner(User $actor, LedgerOwner $owner, LedgerTransaction $transaction, array $data): LedgerTransaction
    {
        $owner->assertOwns($transaction);
        $this->ensureTransactionIsManuallyEditable($transaction);

        return DB::transaction(function () use ($actor, $owner, $transaction, $data) {
            $account = $owner->resolveAccount($data['ledger_account_id'] ?? null);
            $wipeCycle = $this->cycles->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $amount = $this->wholeNumber($data['amount']);

            $this->assertTransactionAmountIsValid($data['type'], $amount, $owner instanceof PersonalLedgerOwner);

            $transaction->forceFill([
                'ledger_account_id' => $account->id,
                'wipe_cycle_id' => $wipeCycle->id,
                'type' => $data['type'],
                'amount' => $amount,
                'currency' => $data['currency'] ?? $account->currency ?? 'aUEC',
                'source_type' => $data['source_type'] ?? null,
                'description' => $data['description'],
                'transaction_date' => $data['transaction_date'] ?? now(),
                'related_ship_asset_id' => $owner->resolveShipAssetId($data['related_ship_asset_id'] ?? null),
                'related_operation_id' => $data['related_operation_id'] ?? null,
                'related_uex_type' => $data['related_uex_type'] ?? null,
                'related_uex_id' => $data['related_uex_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->logActivity($actor, $owner, $wipeCycle, 'transaction.updated', $transaction, [
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'description' => $transaction->description,
            ]);

            return $transaction->fresh(['account:id,name', 'shipAsset:id,custom_name,serial_or_label,vehicle_uex_id']);
        });
    }

    protected function deleteTransactionForOwner(User $actor, LedgerOwner $owner, LedgerTransaction $transaction): void
    {
        $owner->assertOwns($transaction);
        $this->ensureTransactionIsManuallyEditable($transaction);

        $this->logActivity($actor, $owner, $transaction->wipeCycle, 'transaction.deleted', $transaction, [
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'description' => $transaction->description,
        ]);

        $transaction->delete();
    }

    protected function createTradeForOwner(User $actor, LedgerOwner $owner, array $data): LedgerTrade
    {
        return DB::transaction(function () use ($actor, $owner, $data) {
            $account = $owner->resolveAccount($data['ledger_account_id'] ?? null);
            $wipeCycle = $this->cycles->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $quantity = $this->wholeNumber($data['quantity']);
            $buyPrice = $this->wholeNumber($data['buy_price_per_unit']);
            $sellPrice = $this->wholeNumber($data['sell_price_per_unit']);

            if ($quantity <= 0 || $buyPrice < 0 || $sellPrice < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Trade quantities and prices must be valid positive values.',
                ]);
            }

            $totalCost = $quantity * $buyPrice;
            $totalRevenue = $quantity * $sellPrice;
            $profit = $totalRevenue - $totalCost;
            $profitPerUnit = $sellPrice - $buyPrice;

            $trade = LedgerTrade::query()->create($owner->applyOwnership([
                'ledger_account_id' => $account->id,
                'wipe_cycle_id' => $wipeCycle->id,
                'commodity_uex_id' => $data['commodity_uex_id'] ?? null,
                'buy_terminal_uex_id' => $data['buy_terminal_uex_id'] ?? null,
                'sell_terminal_uex_id' => $data['sell_terminal_uex_id'] ?? null,
                'quantity' => $quantity,
                'unit_type' => $data['unit_type'] ?? 'SCU',
                'buy_price_per_unit' => $buyPrice,
                'sell_price_per_unit' => $sellPrice,
                'total_cost' => $totalCost,
                'total_revenue' => $totalRevenue,
                'profit' => $profit,
                'profit_per_unit' => $profitPerUnit,
                'ship_asset_id' => $owner->resolveShipAssetId($data['ship_asset_id'] ?? null),
                'cargo_capacity_used' => $this->nullableWholeNumber($data['cargo_capacity_used'] ?? null),
                'trade_date' => $data['trade_date'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]));

            $this->logActivity($actor, $owner, $wipeCycle, 'trade.created', $trade, [
                'quantity' => $trade->quantity,
                'profit' => $trade->profit,
            ]);

            return $trade;
        });
    }

    protected function updateTradeForOwner(User $actor, LedgerOwner $owner, LedgerTrade $trade, array $data): LedgerTrade
    {
        $owner->assertOwns($trade);

        return DB::transaction(function () use ($actor, $owner, $trade, $data) {
            $account = $owner->resolveAccount($data['ledger_account_id'] ?? null);
            $wipeCycle = $this->cycles->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $quantity = $this->wholeNumber($data['quantity']);
            $buyPrice = $this->wholeNumber($data['buy_price_per_unit']);
            $sellPrice = $this->wholeNumber($data['sell_price_per_unit']);

            if ($quantity <= 0 || $buyPrice < 0 || $sellPrice < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Trade quantities and prices must be valid positive values.',
                ]);
            }

            $totalCost = $quantity * $buyPrice;
            $totalRevenue = $quantity * $sellPrice;
            $profit = $totalRevenue - $totalCost;
            $profitPerUnit = $sellPrice - $buyPrice;

            $trade->forceFill([
                'ledger_account_id' => $account->id,
                'wipe_cycle_id' => $wipeCycle->id,
                'commodity_uex_id' => $data['commodity_uex_id'] ?? null,
                'buy_terminal_uex_id' => $data['buy_terminal_uex_id'] ?? null,
                'sell_terminal_uex_id' => $data['sell_terminal_uex_id'] ?? null,
                'quantity' => $quantity,
                'unit_type' => $data['unit_type'] ?? 'SCU',
                'buy_price_per_unit' => $buyPrice,
                'sell_price_per_unit' => $sellPrice,
                'total_cost' => $totalCost,
                'total_revenue' => $totalRevenue,
                'profit' => $profit,
                'profit_per_unit' => $profitPerUnit,
                'ship_asset_id' => $owner->resolveShipAssetId($data['ship_asset_id'] ?? null),
                'cargo_capacity_used' => $this->nullableWholeNumber($data['cargo_capacity_used'] ?? null),
                'trade_date' => $data['trade_date'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->logActivity($actor, $owner, $wipeCycle, 'trade.updated', $trade, [
                'quantity' => $trade->quantity,
                'profit' => $trade->profit,
            ]);

            return $trade->fresh(['account:id,name', 'shipAsset:id,custom_name,serial_or_label,vehicle_uex_id']);
        });
    }

    protected function deleteTradeForOwner(User $actor, LedgerOwner $owner, LedgerTrade $trade): void
    {
        $owner->assertOwns($trade);

        $this->logActivity($actor, $owner, $trade->wipeCycle, 'trade.deleted', $trade, [
            'quantity' => $trade->quantity,
            'profit' => $trade->profit,
        ]);

        $trade->delete();
    }

    protected function createInventoryItemForOwner(User $actor, LedgerOwner $owner, array $data): LedgerInventoryItem
    {
        return DB::transaction(function () use ($actor, $owner, $data) {
            $wipeCycle = $this->cycles->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $sourceType = $data['source_type'];
            $referenceId = $data['uex_reference_id'] ?? null;
            $isPersonal = $owner instanceof PersonalLedgerOwner;
            $isOrg = $owner instanceof OrgLedgerOwner;

            if ($sourceType !== 'custom' && blank($referenceId)) {
                throw ValidationException::withMessages([
                    'uex_reference_id' => 'Pick a cached UEX reference or switch this inventory record to custom.',
                ]);
            }

            if ($sourceType === 'custom' && blank($data['custom_name'] ?? null)) {
                throw ValidationException::withMessages([
                    'custom_name' => 'Custom inventory records need a name.',
                ]);
            }

            $attributes = [
                'wipe_cycle_id' => $wipeCycle->id,
                'source_type' => $sourceType,
                'uex_reference_type' => $isPersonal
                    ? ($data['uex_reference_type'] ?? $sourceType)
                    : ($data['uex_reference_type'] ?? ($sourceType === 'custom' ? null : $sourceType)),
                'uex_reference_id' => $referenceId,
                'custom_name' => $data['custom_name'] ?? null,
                'category' => $data['category'] ?? null,
                'quantity' => $this->wholeNumber($isPersonal ? ($data['quantity'] ?? 1) : $data['quantity']),
                'unit_label' => $data['unit_label'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'terminal_uex_id' => $data['terminal_uex_id'] ?? null,
                'assigned_ship_asset_id' => $owner->resolveShipAssetId($data['assigned_ship_asset_id'] ?? null),
                'purchase_price' => $this->nullableWholeNumber($data['purchase_price'] ?? null),
                'estimated_value' => $this->nullableWholeNumber($data['estimated_value'] ?? null),
                'currency' => $data['currency'] ?? 'aUEC',
                'status' => $data['status'] ?? 'owned',
                'acquired_at' => $data['acquired_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ];

            if (! $isOrg) {
                $attributes['related_operation_id'] = $data['related_operation_id'] ?? null;
                $attributes['operation_settlement_id'] = $data['operation_settlement_id'] ?? null;
                $attributes['provenance_locked'] = (bool) ($data['provenance_locked'] ?? false);
            }

            $item = LedgerInventoryItem::query()->create($owner->applyOwnership($attributes));

            $this->logActivity($actor, $owner, $wipeCycle, 'inventory.created', $item, [
                'source_type' => $item->source_type,
                'quantity' => $item->quantity,
            ]);

            return $item;
        });
    }

    protected function updateInventoryItemForOwner(User $actor, LedgerOwner $owner, LedgerInventoryItem $item, array $data): LedgerInventoryItem
    {
        $owner->assertOwns($item);

        return DB::transaction(function () use ($actor, $owner, $item, $data) {
            $wipeCycle = $this->cycles->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $sourceType = $data['source_type'];
            $referenceId = $data['uex_reference_id'] ?? null;
            $isPersonal = $owner instanceof PersonalLedgerOwner;
            $isOrg = $owner instanceof OrgLedgerOwner;

            if ($sourceType !== 'custom' && blank($referenceId)) {
                throw ValidationException::withMessages([
                    'uex_reference_id' => 'Pick a cached UEX reference or switch this inventory record to custom.',
                ]);
            }

            if ($sourceType === 'custom' && blank($data['custom_name'] ?? null)) {
                throw ValidationException::withMessages([
                    'custom_name' => 'Custom inventory records need a name.',
                ]);
            }

            $attributes = [
                'wipe_cycle_id' => $wipeCycle->id,
                'source_type' => $sourceType,
                'uex_reference_type' => $isPersonal
                    ? ($data['uex_reference_type'] ?? $sourceType)
                    : ($data['uex_reference_type'] ?? ($sourceType === 'custom' ? null : $sourceType)),
                'uex_reference_id' => $referenceId,
                'custom_name' => $data['custom_name'] ?? null,
                'category' => $data['category'] ?? null,
                'quantity' => $this->wholeNumber($isPersonal ? ($data['quantity'] ?? 1) : $data['quantity']),
                'unit_label' => $data['unit_label'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'terminal_uex_id' => $data['terminal_uex_id'] ?? null,
                'assigned_ship_asset_id' => $owner->resolveShipAssetId($data['assigned_ship_asset_id'] ?? null),
                'purchase_price' => $this->nullableWholeNumber($data['purchase_price'] ?? null),
                'estimated_value' => $this->nullableWholeNumber($data['estimated_value'] ?? null),
                'currency' => $data['currency'] ?? 'aUEC',
                'status' => $data['status'] ?? 'owned',
                'acquired_at' => $data['acquired_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ];

            if (! $isOrg) {
                $attributes['related_operation_id'] = $data['related_operation_id'] ?? null;
                $attributes['operation_settlement_id'] = $data['operation_settlement_id'] ?? null;
            }

            $item->forceFill($attributes)->save();

            $this->logActivity($actor, $owner, $wipeCycle, 'inventory.updated', $item, [
                'source_type' => $item->source_type,
                'quantity' => $item->quantity,
            ]);

            return $item->fresh(['assignedShipAsset:id,custom_name,serial_or_label,vehicle_uex_id']);
        });
    }

    protected function deleteInventoryItemForOwner(User $actor, LedgerOwner $owner, LedgerInventoryItem $item): void
    {
        $owner->assertOwns($item);

        $this->logActivity($actor, $owner, $item->wipeCycle, 'inventory.deleted', $item, [
            'source_type' => $item->source_type,
            'quantity' => $item->quantity,
        ]);

        $item->delete();
    }

    protected function createShipAssetForOwner(User $actor, LedgerOwner $owner, array $data): LedgerShipAsset
    {
        return DB::transaction(function () use ($actor, $owner, $data) {
            $wipeCycle = $this->cycles->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);

            $asset = LedgerShipAsset::query()->create($owner->applyOwnership([
                'wipe_cycle_id' => $wipeCycle->id,
                'vehicle_uex_id' => $data['vehicle_uex_id'] ?? null,
                'custom_name' => $data['custom_name'] ?? null,
                'serial_or_label' => $data['serial_or_label'] ?? null,
                'purchase_price' => $this->nullableWholeNumber($data['purchase_price'] ?? null),
                'currency' => $data['currency'] ?? 'aUEC',
                'acquisition_source' => $data['acquisition_source'] ?? null,
                'current_location' => $data['current_location'] ?? null,
                'status' => $data['status'] ?? 'owned',
                'acquired_at' => $data['acquired_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]));

            $this->logActivity($actor, $owner, $wipeCycle, 'ship_asset.created', $asset, [
                'vehicle_uex_id' => $asset->vehicle_uex_id,
                'status' => $asset->status,
            ]);

            return $asset;
        });
    }

    protected function updateShipAssetForOwner(User $actor, LedgerOwner $owner, LedgerShipAsset $asset, array $data): LedgerShipAsset
    {
        $owner->assertOwns($asset);

        return DB::transaction(function () use ($actor, $owner, $asset, $data) {
            $wipeCycle = $this->cycles->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);

            $asset->forceFill([
                'wipe_cycle_id' => $wipeCycle->id,
                'vehicle_uex_id' => $data['vehicle_uex_id'] ?? null,
                'custom_name' => $data['custom_name'] ?? null,
                'serial_or_label' => $data['serial_or_label'] ?? null,
                'purchase_price' => $this->nullableWholeNumber($data['purchase_price'] ?? null),
                'currency' => $data['currency'] ?? 'aUEC',
                'acquisition_source' => $data['acquisition_source'] ?? null,
                'current_location' => $data['current_location'] ?? null,
                'status' => $data['status'] ?? 'owned',
                'acquired_at' => $data['acquired_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->logActivity($actor, $owner, $wipeCycle, 'ship_asset.updated', $asset, [
                'vehicle_uex_id' => $asset->vehicle_uex_id,
                'status' => $asset->status,
            ]);

            return $asset->fresh();
        });
    }

    protected function deleteShipAssetForOwner(User $actor, LedgerOwner $owner, LedgerShipAsset $asset): void
    {
        $owner->assertOwns($asset);

        $this->logActivity($actor, $owner, $asset->wipeCycle, 'ship_asset.deleted', $asset, [
            'vehicle_uex_id' => $asset->vehicle_uex_id,
            'status' => $asset->status,
        ]);

        $asset->delete();
    }

    protected function assertTransactionAmountIsValid(string $type, float $amount, bool $isPersonalOwner): void
    {
        if ($type === 'adjustment') {
            if ($amount === 0.0) {
                throw ValidationException::withMessages([
                    'amount' => 'Adjustment amount cannot be zero.',
                ]);
            }
        } elseif ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Income and expense amounts must be greater than zero.',
            ]);
        }

        if ($type !== 'adjustment' && $amount < 0) {
            throw ValidationException::withMessages([
                'amount' => $isPersonalOwner
                    ? 'Only manual adjustments may be negative.'
                    : 'Only adjustments may use negative values.',
            ]);
        }
    }

    protected function ensureTransactionIsManuallyEditable(LedgerTransaction $transaction): void
    {
        if ((bool) $transaction->provenance_locked) {
            throw ValidationException::withMessages([
                'transaction' => 'Settlement and transfer records stay locked so their audit trail stays intact. Create a new correcting entry or reopen the source workflow instead.',
            ]);
        }

        if (! in_array($transaction->source_type, ['transfer', 'transfer_reversal'], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'transaction' => 'Transfer records stay locked so the paired ledgers stay in sync. Create a new transfer or reversal instead.',
        ]);
    }

    protected function wholeNumber(mixed $value): int
    {
        return (int) round((float) $value);
    }

    protected function nullableWholeNumber(mixed $value): ?int
    {
        if (blank($value)) {
            return null;
        }

        return $this->wholeNumber($value);
    }

    protected function logActivity(
        User $actor,
        LedgerOwner $owner,
        ?WipeCycle $wipeCycle,
        string $action,
        object $target,
        array $metadata = []
    ): void {
        [$subjectUser, $squadron, $isOrgOwned] = $owner->activityContext();

        LedgerActivityLog::query()->create([
            'actor_user_id' => $actor->id,
            'subject_user_id' => $subjectUser?->id,
            'squadron_id' => $squadron?->id,
            'is_org_owned' => $isOrgOwned,
            'wipe_cycle_id' => $wipeCycle?->id,
            'action' => $action,
            'target_type' => class_basename($target),
            'target_id' => $target->id ?? null,
            'metadata' => $metadata,
            'created_at' => CarbonImmutable::now(),
        ]);
    }

    protected function personalOwner(User $owner): PersonalLedgerOwner
    {
        return new PersonalLedgerOwner($owner, $this->cycles);
    }

    protected function squadronOwner(User $actor, Squadron $squadron): SquadronLedgerOwner
    {
        return new SquadronLedgerOwner($squadron, $actor, $this->cycles);
    }

    protected function orgOwner(User $actor): OrgLedgerOwner
    {
        return new OrgLedgerOwner($actor, $this->cycles);
    }
}
