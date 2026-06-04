<?php

namespace App\Services;

use App\Models\LedgerActivityLog;
use App\Models\LedgerInventoryItem;
use App\Models\LedgerShipAsset;
use App\Models\LedgerTrade;
use App\Models\LedgerTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LedgerReferenceService
{
    public function referenceOptions(): array
    {
        return [
            'ships' => DB::table('uex_vehicles')
                ->select('uex_id', 'name', 'full_name', 'type')
                ->orderByRaw("LOWER(COALESCE(full_name, name, ''))")
                ->limit(500)
                ->get()
                ->map(fn ($row) => [
                    'uex_id' => (int) $row->uex_id,
                    'name' => $row->full_name ?: $row->name ?: 'Unnamed ship',
                    'type' => $row->type,
                ])
                ->all(),
            'commodities' => DB::table('uex_commodities')
                ->select('uex_id', 'name', 'code')
                ->orderByRaw("LOWER(COALESCE(name, code, ''))")
                ->get()
                ->map(fn ($row) => [
                    'uex_id' => (int) $row->uex_id,
                    'name' => $row->name ?: $row->code ?: 'Unknown commodity',
                ])
                ->all(),
            'items' => DB::table('uex_items')
                ->select('uex_id', 'name', 'type', 'category_uex_id')
                ->orderByRaw("LOWER(COALESCE(name, ''))")
                ->get()
                ->map(fn ($row) => [
                    'uex_id' => (int) $row->uex_id,
                    'name' => $row->name ?: "Item {$row->uex_id}",
                    'type' => $row->type,
                    'category_uex_id' => $row->category_uex_id ? (int) $row->category_uex_id : null,
                ])
                ->all(),
            'tradePricing' => $this->tradePricingSuggestions(),
            'inventoryValuations' => $this->inventoryValuationSuggestions(),
            'shipPricing' => $this->shipPricingSuggestions(),
        ];
    }

    public function tradePricingSuggestions(): array
    {
        $lowestBuyRows = $this->bestPriceRows('uex_commodity_prices', 'commodity_uex_id', 'price_buy');
        $highestSellRows = $this->bestPriceRows('uex_commodity_prices', 'commodity_uex_id', 'price_sell', 'desc');

        return $lowestBuyRows->keys()
            ->merge($highestSellRows->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(function ($uexId) use ($lowestBuyRows, $highestSellRows) {
                $buyRow = $lowestBuyRows->get($uexId);
                $sellRow = $highestSellRows->get($uexId);

                return [
                    'uex_id' => (int) $uexId,
                    'buy_price_per_unit' => $buyRow ? (float) $buyRow->price_buy : null,
                    'buy_terminal_name' => $buyRow?->terminal_name,
                    'sell_price_per_unit' => $sellRow ? (float) $sellRow->price_sell : null,
                    'sell_terminal_name' => $sellRow?->terminal_name,
                ];
            })
            ->all();
    }

    public function inventoryValuationSuggestions(): array
    {
        $lowestCommodityBuyRows = $this->bestPriceRows('uex_commodity_prices', 'commodity_uex_id', 'price_buy');
        $highestCommoditySellRows = $this->bestPriceRows('uex_commodity_prices', 'commodity_uex_id', 'price_sell', 'desc');
        $lowestItemBuyRows = $this->bestPriceRows('uex_item_prices', 'item_uex_id', 'price_buy');
        $highestItemSellRows = $this->bestPriceRows('uex_item_prices', 'item_uex_id', 'price_sell', 'desc');
        $itemTypes = DB::table('uex_items')->pluck('type', 'uex_id');

        return [
            'commodities' => $lowestCommodityBuyRows->keys()
                ->merge($highestCommoditySellRows->keys())
                ->unique()
                ->sort()
                ->values()
                ->map(function ($uexId) use ($lowestCommodityBuyRows, $highestCommoditySellRows) {
                    $buyRow = $lowestCommodityBuyRows->get($uexId);
                    $sellRow = $highestCommoditySellRows->get($uexId);

                    return [
                        'uex_id' => (int) $uexId,
                        'category' => 'Commodity',
                        'unit_label' => 'SCU',
                        'unit_purchase_price' => $buyRow ? (float) $buyRow->price_buy : null,
                        'purchase_terminal_name' => $buyRow?->terminal_name,
                        'unit_estimated_value' => $sellRow ? (float) $sellRow->price_sell : null,
                        'estimated_terminal_name' => $sellRow?->terminal_name,
                    ];
                })
                ->all(),
            'items' => $lowestItemBuyRows->keys()
                ->merge($highestItemSellRows->keys())
                ->merge($itemTypes->keys())
                ->unique()
                ->sort()
                ->values()
                ->map(function ($uexId) use ($lowestItemBuyRows, $highestItemSellRows, $itemTypes) {
                    $buyRow = $lowestItemBuyRows->get($uexId);
                    $sellRow = $highestItemSellRows->get($uexId);

                    return [
                        'uex_id' => (int) $uexId,
                        'category' => $itemTypes->get((int) $uexId) ?: 'Item',
                        'unit_label' => 'units',
                        'unit_purchase_price' => $buyRow ? (float) $buyRow->price_buy : null,
                        'purchase_terminal_name' => $buyRow?->terminal_name,
                        'unit_estimated_value' => $sellRow ? (float) $sellRow->price_sell : null,
                        'estimated_terminal_name' => $sellRow?->terminal_name,
                    ];
                })
                ->all(),
        ];
    }

    public function shipPricingSuggestions(): array
    {
        $lowestPurchaseRows = $this->bestPriceRows('uex_vehicle_purchase_prices', 'vehicle_uex_id', 'price_buy');
        $lowestRentalRows = $this->bestPriceRows('uex_vehicle_rental_prices', 'vehicle_uex_id', 'price_rent');

        return $lowestPurchaseRows->keys()
            ->merge($lowestRentalRows->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(function ($uexId) use ($lowestPurchaseRows, $lowestRentalRows) {
                $purchaseRow = $lowestPurchaseRows->get($uexId);
                $rentalRow = $lowestRentalRows->get($uexId);

                return [
                    'uex_id' => (int) $uexId,
                    'purchase_price' => $purchaseRow ? (float) $purchaseRow->price_buy : null,
                    'purchase_terminal_name' => $purchaseRow?->terminal_name,
                    'rental_price' => $rentalRow ? (float) $rentalRow->price_rent : null,
                    'rental_terminal_name' => $rentalRow?->terminal_name,
                ];
            })
            ->all();
    }

    public function bestPriceRows(
        string $table,
        string $groupColumn,
        string $priceColumn,
        string $direction = 'asc'
    ): Collection {
        $query = DB::table($table)
            ->select($groupColumn, $priceColumn, 'terminal_name')
            ->whereNotNull($groupColumn)
            ->whereNotNull($priceColumn)
            ->where($priceColumn, '>', 0)
            ->orderBy($groupColumn);

        if ($direction === 'desc') {
            $query->orderByDesc($priceColumn);
        } else {
            $query->orderBy($priceColumn);
        }

        return $query
            ->get()
            ->unique(fn ($row) => (int) $row->{$groupColumn})
            ->mapWithKeys(fn ($row) => [
                (int) $row->{$groupColumn} => $row,
            ]);
    }

    public function referenceMaps(): array
    {
        $vehicleNames = DB::table('uex_vehicles')
            ->select('uex_id', 'full_name', 'name')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->uex_id => $row->full_name ?: $row->name ?: "Ship {$row->uex_id}",
            ]);

        return [
            'vehicles' => $vehicleNames,
            'commodities' => DB::table('uex_commodities')->pluck('name', 'uex_id'),
            'items' => DB::table('uex_items')->pluck('name', 'uex_id'),
            'terminals' => DB::table('uex_terminals')->pluck('full_name', 'uex_id'),
        ];
    }

    public function suggestionMap(array $entries): Collection
    {
        return collect($entries)->mapWithKeys(fn (array $entry) => [
            (int) $entry['uex_id'] => $entry,
        ]);
    }

    public function inventorySuggestionMaps(array $references): array
    {
        $inventoryValuations = $references['inventoryValuations'] ?? [];

        return [
            'commodities' => $this->suggestionMap($inventoryValuations['commodities'] ?? []),
            'items' => $this->suggestionMap($inventoryValuations['items'] ?? []),
        ];
    }

    public function inventorySuggestionForItem(LedgerInventoryItem $item, array $inventorySuggestionMaps): ?array
    {
        $referenceId = $item->uex_reference_id ? (int) $item->uex_reference_id : null;

        if (! $referenceId) {
            return null;
        }

        if ($item->source_type === 'commodity') {
            return $inventorySuggestionMaps['commodities']->get($referenceId);
        }

        if (in_array($item->source_type, ['item', 'component'], true)) {
            return $inventorySuggestionMaps['items']->get($referenceId);
        }

        return null;
    }

    public function inventoryEstimateMatchesSuggestion(LedgerInventoryItem $item, array $inventorySuggestionMaps): bool
    {
        $suggestion = $this->inventorySuggestionForItem($item, $inventorySuggestionMaps);
        $expectedValue = $suggestion['unit_estimated_value'] ?? null;

        if ($item->estimated_value === null || $expectedValue === null) {
            return false;
        }

        return round((float) $item->estimated_value, 2) === round((float) $expectedValue * (float) $item->quantity, 2);
    }

    public function inventoryPurchaseMatchesSuggestion(LedgerInventoryItem $item, array $inventorySuggestionMaps): bool
    {
        $suggestion = $this->inventorySuggestionForItem($item, $inventorySuggestionMaps);
        $expectedValue = $suggestion['unit_purchase_price'] ?? null;

        if ($item->purchase_price === null || $expectedValue === null) {
            return false;
        }

        return round((float) $item->purchase_price, 2) === round((float) $expectedValue * (float) $item->quantity, 2);
    }

    public function shipPurchaseMatchesSuggestion(LedgerShipAsset $asset, Collection $shipPricingMap): bool
    {
        $suggestion = $asset->vehicle_uex_id ? $shipPricingMap->get((int) $asset->vehicle_uex_id) : null;
        $expectedValue = $suggestion['purchase_price'] ?? null;

        if ($asset->purchase_price === null || $expectedValue === null) {
            return false;
        }

        return round((float) $asset->purchase_price, 2) === round((float) $expectedValue, 2);
    }

    public function transferInventoryOptions(Collection $items, array $referenceMaps): array
    {
        return $items
            ->sortBy(fn (LedgerInventoryItem $item) => strtolower(
                $item->custom_name
                ?: $this->resolveReferenceLabel($item->uex_reference_type, $item->uex_reference_id, $referenceMaps)
                ?: 'zzzz'
            ))
            ->values()
            ->map(function (LedgerInventoryItem $item) use ($referenceMaps) {
                $label = $item->custom_name
                    ?: $this->resolveReferenceLabel($item->uex_reference_type, $item->uex_reference_id, $referenceMaps)
                    ?: 'Custom inventory';

                return [
                    'id' => $item->id,
                    'label' => $label,
                    'quantity' => (float) $item->quantity,
                    'unit_label' => $item->unit_label ?: 'units',
                    'status' => $item->status,
                ];
            })
            ->all();
    }

    public function presentTransaction(LedgerTransaction $transaction, array $referenceMaps): array
    {
        return [
            'id' => $transaction->id,
            'ledger_account_id' => $transaction->ledger_account_id,
            'type' => $transaction->type,
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency,
            'source_type' => $transaction->source_type,
            'is_transfer' => $transaction->source_type === 'transfer',
            'description' => $transaction->description,
            'transaction_date' => $transaction->transaction_date?->toIso8601String(),
            'wipe_cycle_id' => $transaction->wipe_cycle_id,
            'related_ship_asset_id' => $transaction->related_ship_asset_id,
            'related_operation_id' => $transaction->related_operation_id,
            'related_uex_type' => $transaction->related_uex_type,
            'related_uex_id' => $transaction->related_uex_id,
            'related_reference' => $this->resolveReferenceLabel(
                $transaction->related_uex_type,
                $transaction->related_uex_id,
                $referenceMaps
            ),
            'ship_asset' => $transaction->shipAsset
                ? ($transaction->shipAsset->custom_name ?: $transaction->shipAsset->serial_or_label ?: "Ship {$transaction->shipAsset->id}")
                : null,
            'notes' => $transaction->notes,
        ];
    }

    public function presentTrade(LedgerTrade $trade, array $referenceMaps): array
    {
        return [
            'id' => $trade->id,
            'ledger_account_id' => $trade->ledger_account_id,
            'wipe_cycle_id' => $trade->wipe_cycle_id,
            'commodity_uex_id' => $trade->commodity_uex_id,
            'buy_terminal_uex_id' => $trade->buy_terminal_uex_id,
            'sell_terminal_uex_id' => $trade->sell_terminal_uex_id,
            'commodity' => $this->resolveReferenceLabel('commodity', $trade->commodity_uex_id, $referenceMaps),
            'buy_terminal' => $this->resolveReferenceLabel('terminal', $trade->buy_terminal_uex_id, $referenceMaps),
            'sell_terminal' => $this->resolveReferenceLabel('terminal', $trade->sell_terminal_uex_id, $referenceMaps),
            'quantity' => (float) $trade->quantity,
            'unit_type' => $trade->unit_type,
            'buy_price_per_unit' => (float) $trade->buy_price_per_unit,
            'sell_price_per_unit' => (float) $trade->sell_price_per_unit,
            'total_cost' => (float) $trade->total_cost,
            'total_revenue' => (float) $trade->total_revenue,
            'profit' => (float) $trade->profit,
            'profit_per_unit' => (float) $trade->profit_per_unit,
            'ship_asset_id' => $trade->ship_asset_id,
            'cargo_capacity_used' => $trade->cargo_capacity_used !== null ? (float) $trade->cargo_capacity_used : null,
            'trade_date' => $trade->trade_date?->toIso8601String(),
            'ship_asset' => $trade->shipAsset
                ? ($trade->shipAsset->custom_name ?: $trade->shipAsset->serial_or_label ?: "Ship {$trade->shipAsset->id}")
                : null,
            'notes' => $trade->notes,
        ];
    }

    public function presentInventoryItem(
        LedgerInventoryItem $item,
        array $referenceMaps,
        array $inventorySuggestionMaps
    ): array {
        $estimateMatchesSuggestion = $this->inventoryEstimateMatchesSuggestion($item, $inventorySuggestionMaps);
        $purchaseMatchesSuggestion = $this->inventoryPurchaseMatchesSuggestion($item, $inventorySuggestionMaps);

        return [
            'id' => $item->id,
            'wipe_cycle_id' => $item->wipe_cycle_id,
            'source_type' => $item->source_type,
            'uex_reference_type' => $item->uex_reference_type,
            'uex_reference_id' => $item->uex_reference_id,
            'custom_name' => $item->custom_name,
            'reference_label' => $item->custom_name ?: $this->resolveReferenceLabel(
                $item->uex_reference_type,
                $item->uex_reference_id,
                $referenceMaps
            ),
            'category' => $item->category,
            'quantity' => (float) $item->quantity,
            'unit_label' => $item->unit_label,
            'location_name' => $item->location_name,
            'terminal_uex_id' => $item->terminal_uex_id,
            'terminal_name' => $this->resolveReferenceLabel('terminal', $item->terminal_uex_id, $referenceMaps),
            'purchase_price' => $item->purchase_price !== null ? (float) $item->purchase_price : null,
            'purchase_price_source' => $purchaseMatchesSuggestion ? 'uex_estimate' : 'manual',
            'estimated_value' => $item->estimated_value !== null ? (float) $item->estimated_value : null,
            'estimated_value_source' => $estimateMatchesSuggestion ? 'uex_estimate' : 'manual',
            'currency' => $item->currency,
            'status' => $item->status,
            'assigned_ship_asset_id' => $item->assigned_ship_asset_id,
            'assigned_ship' => $item->assignedShipAsset
                ? ($item->assignedShipAsset->custom_name ?: $item->assignedShipAsset->serial_or_label ?: "Ship {$item->assignedShipAsset->id}")
                : null,
            'acquired_at' => $item->acquired_at?->toIso8601String(),
            'notes' => $item->notes,
        ];
    }

    public function presentShipAsset(
        LedgerShipAsset $asset,
        array $referenceMaps,
        Collection $shipPricingMap
    ): array {
        $purchaseMatchesSuggestion = $this->shipPurchaseMatchesSuggestion($asset, $shipPricingMap);

        return [
            'id' => $asset->id,
            'wipe_cycle_id' => $asset->wipe_cycle_id,
            'vehicle_uex_id' => $asset->vehicle_uex_id,
            'custom_name' => $asset->custom_name,
            'ship_name' => $asset->custom_name ?: $this->resolveReferenceLabel('vehicle', $asset->vehicle_uex_id, $referenceMaps),
            'serial_or_label' => $asset->serial_or_label,
            'purchase_price' => $asset->purchase_price !== null ? (float) $asset->purchase_price : null,
            'purchase_price_source' => $purchaseMatchesSuggestion ? 'uex_estimate' : 'manual',
            'currency' => $asset->currency,
            'acquisition_source' => $asset->acquisition_source,
            'current_location' => $asset->current_location,
            'status' => $asset->status,
            'acquired_at' => $asset->acquired_at?->toIso8601String(),
            'notes' => $asset->notes,
        ];
    }

    public function presentActivityLog(LedgerActivityLog $log): array
    {
        $metadata = $log->metadata ?? [];
        $title = match ($log->action) {
            'transaction.created' => 'Logged a transaction',
            'transaction.updated' => 'Updated a transaction',
            'transaction.deleted' => 'Deleted a transaction',
            'transfer.created' => 'Transferred funds',
            'inventory.transfer_out' => 'Moved inventory out',
            'inventory.transfer_in' => 'Received inventory',
            'trade.created' => 'Logged a trade run',
            'trade.updated' => 'Updated a trade run',
            'trade.deleted' => 'Deleted a trade run',
            'inventory.created' => 'Added an inventory record',
            'inventory.updated' => 'Updated an inventory record',
            'inventory.deleted' => 'Deleted an inventory record',
            'ship_asset.created' => 'Added a ship record',
            'ship_asset.updated' => 'Updated a ship record',
            'ship_asset.deleted' => 'Deleted a ship record',
            'wipe_cycle.created' => 'Started a new cycle',
            'wipe_cycle.current_set' => 'Changed the current cycle',
            'wipe_cycle.closed' => 'Closed a cycle',
            'wipe_cycle.updated' => 'Updated the current cycle',
            default => 'Updated the ledger',
        };

        $detail = match ($log->action) {
            'transaction.created', 'transaction.updated', 'transaction.deleted' => $metadata['description'] ?? null,
            'transfer.created' => isset($metadata['from_label'], $metadata['to_label'])
                ? "{$metadata['from_label']} -> {$metadata['to_label']}"
                : ($metadata['description'] ?? null),
            'inventory.transfer_out', 'inventory.transfer_in' => isset($metadata['item_label'], $metadata['quantity'])
                ? $metadata['item_label'] . ' • ' . rtrim(rtrim(number_format((float) $metadata['quantity'], 4, '.', ''), '0'), '.')
                : null,
            'trade.created', 'trade.updated', 'trade.deleted' => isset($metadata['profit'])
                ? 'Trade profit: ' . number_format((float) $metadata['profit'], 2) . ' aUEC'
                : null,
            'inventory.created', 'inventory.updated', 'inventory.deleted' => isset($metadata['quantity'])
                ? 'Quantity: ' . rtrim(rtrim(number_format((float) $metadata['quantity'], 4, '.', ''), '0'), '.')
                : null,
            'ship_asset.created', 'ship_asset.updated', 'ship_asset.deleted' => $metadata['status'] ?? null,
            'wipe_cycle.created', 'wipe_cycle.current_set', 'wipe_cycle.closed', 'wipe_cycle.updated' => $metadata['name'] ?? null,
            default => null,
        };

        return [
            'id' => $log->id,
            'title' => $title,
            'detail' => $detail,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    public function resolveReferenceLabel(?string $type, $id, array $referenceMaps): ?string
    {
        if (! $id || ! $type) {
            return null;
        }

        return match ($type) {
            'vehicle', 'ship' => $referenceMaps['vehicles'][$id] ?? null,
            'commodity' => $referenceMaps['commodities'][$id] ?? null,
            'item', 'component' => $referenceMaps['items'][$id] ?? null,
            'terminal' => $referenceMaps['terminals'][$id] ?? null,
            default => null,
        };
    }
}
