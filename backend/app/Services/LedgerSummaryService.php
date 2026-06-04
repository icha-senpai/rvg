<?php

namespace App\Services;

use App\Models\LedgerInventoryItem;
use App\Models\LedgerShipAsset;
use App\Models\LedgerTrade;
use App\Models\WipeCycle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LedgerSummaryService
{
    public function __construct(
        protected LedgerReferenceService $references,
    ) {}

    public function summarizeLedgerCollections(
        Collection $transactions,
        Collection $trades,
        Collection $inventoryItems,
        Collection $shipAssets,
        array $inventorySuggestionMaps,
        Collection $shipPricingMap,
    ): array {
        $tradeProfit = round((float) $trades->sum('profit'), 2);
        $tradeCost = round((float) $trades->sum('total_cost'), 2);
        $transactionIncome = $this->sumTransactionsByType($transactions, 'income');
        $transactionExpenses = $this->sumTransactionsByType($transactions, 'expense');
        $estimatedNetBalance = round($this->sumBalance($transactions) + $tradeProfit, 2);
        $inventoryValue = round((float) $inventoryItems->sum('estimated_value'), 2);
        $commodityInventoryValue = round((float) $inventoryItems->where('source_type', 'commodity')->sum('estimated_value'), 2);
        $gearInventoryValue = round((float) $inventoryItems
            ->reject(fn (LedgerInventoryItem $item) => $item->source_type === 'commodity')
            ->sum('estimated_value'), 2);
        $fleetValue = round((float) $shipAssets->sum(fn (LedgerShipAsset $asset) => (float) ($asset->purchase_price ?? 0)), 2);
        $inventoryEstimateCount = $inventoryItems
            ->filter(fn (LedgerInventoryItem $item) => $this->references->inventoryEstimateMatchesSuggestion($item, $inventorySuggestionMaps))
            ->count();
        $shipEstimateCount = $shipAssets
            ->filter(fn (LedgerShipAsset $asset) => $this->references->shipPurchaseMatchesSuggestion($asset, $shipPricingMap))
            ->count();

        return [
            'estimated_balance' => $estimatedNetBalance,
            'income' => round($transactionIncome + $tradeProfit, 2),
            'expenses' => round($transactionExpenses + $tradeCost, 2),
            'net_profit' => $estimatedNetBalance,
            'trade_profit' => $tradeProfit,
            'trade_spend' => $tradeCost,
            'inventory_value' => $inventoryValue,
            'commodity_inventory_value' => $commodityInventoryValue,
            'gear_inventory_value' => $gearInventoryValue,
            'fleet_value' => $fleetValue,
            'ships_owned' => $shipAssets->count(),
            'inventory_estimate_count' => $inventoryEstimateCount,
            'ship_estimate_count' => $shipEstimateCount,
            'transaction_count' => $transactions->count(),
            'trade_count' => $trades->count(),
            'inventory_count' => $inventoryItems->count(),
            'ship_count' => $shipAssets->count(),
        ];
    }

    public function buildCycleSummaries(
        Collection $transactions,
        Collection $trades,
        Collection $inventoryItems,
        Collection $shipAssets,
        array $inventorySuggestionMaps,
        Collection $shipPricingMap,
    ): Collection {
        return WipeCycle::query()
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (WipeCycle $wipe) use ($transactions, $trades, $inventoryItems, $shipAssets, $inventorySuggestionMaps, $shipPricingMap) {
                $cycleTransactions = $transactions->where('wipe_cycle_id', $wipe->id)->values();
                $cycleTrades = $trades->where('wipe_cycle_id', $wipe->id)->values();
                $cycleInventoryItems = $inventoryItems->where('wipe_cycle_id', $wipe->id)->values();
                $cycleShipAssets = $shipAssets->where('wipe_cycle_id', $wipe->id)->values();

                return [
                    'id' => $wipe->id,
                    'name' => $wipe->name,
                    'star_citizen_version' => $wipe->star_citizen_version,
                    'wipe_type' => $wipe->wipe_type,
                    'is_current' => $wipe->is_current,
                    'started_at' => $wipe->started_at?->toIso8601String(),
                    'ended_at' => $wipe->ended_at?->toIso8601String(),
                    'cards' => $this->summarizeLedgerCollections(
                        $cycleTransactions,
                        $cycleTrades,
                        $cycleInventoryItems,
                        $cycleShipAssets,
                        $inventorySuggestionMaps,
                        $shipPricingMap
                    ),
                ];
            })
            ->values();
    }

    public function buildCycleComparison(Collection $cycleSummaries, array $filter, WipeCycle $currentWipe): ?array
    {
        if ($cycleSummaries->count() < 2) {
            return null;
        }

        $focusCycleId = $filter['mode'] === 'specific' && $filter['wipe']
            ? $filter['wipe']->id
            : $currentWipe->id;

        $focusIndex = $cycleSummaries->search(fn (array $summary) => $summary['id'] === $focusCycleId);

        if ($focusIndex === false) {
            return null;
        }

        $focus = $cycleSummaries->get($focusIndex);
        $baseline = $cycleSummaries->get($focusIndex + 1);

        if (! $focus || ! $baseline) {
            return null;
        }

        return [
            'focus' => $focus,
            'baseline' => $baseline,
            'metrics' => collect([
                'net_profit' => 'Net Position',
                'trade_profit' => 'Trade Profit',
                'inventory_value' => 'Inventory Value',
                'fleet_value' => 'Fleet Value',
            ])->map(function (string $label, string $key) use ($focus, $baseline) {
                $focusValue = (float) ($focus['cards'][$key] ?? 0);
                $baselineValue = (float) ($baseline['cards'][$key] ?? 0);

                return [
                    'key' => $key,
                    'label' => $label,
                    'focus_value' => round($focusValue, 2),
                    'baseline_value' => round($baselineValue, 2),
                    'delta' => round($focusValue - $baselineValue, 2),
                ];
            })->values()->all(),
        ];
    }

    public function buildReportsForCollections(
        Collection $transactions,
        Collection $trades,
        Collection $inventory,
        Collection $ships,
        array $filter
    ): array {
        $commodityNames = DB::table('uex_commodities')->pluck('name', 'uex_id');

        $wipeCycles = WipeCycle::query()
            ->orderByDesc('started_at')
            ->get();

        if ($filter['mode'] === 'specific' && $filter['wipe']) {
            $wipeCycles = $wipeCycles->where('id', $filter['wipe']->id)->values();
        }

        $profitLossByWipe = $wipeCycles
            ->map(function (WipeCycle $wipe) use ($transactions, $trades) {
                $wipeTransactions = $transactions->where('wipe_cycle_id', $wipe->id);
                $wipeTrades = $trades->where('wipe_cycle_id', $wipe->id);

                return [
                    'label' => $wipe->name,
                    'value' => round($this->sumBalance($wipeTransactions) + (float) $wipeTrades->sum('profit'), 2),
                ];
            })
            ->values()
            ->all();

        return [
            'profitLossByWipe' => $profitLossByWipe,
            'incomeBySource' => $this->groupSum($transactions->where('type', 'income'), 'source_type'),
            'expensesBySource' => $this->groupSum($transactions->where('type', 'expense'), 'source_type'),
            'tradeProfitByCommodity' => $this->tradeProfitBreakdown($trades, $commodityNames)->values()->all(),
            'inventoryValueByCategory' => $this->groupSum($inventory, 'category', 'estimated_value'),
            'shipSummaryByStatus' => $ships
                ->groupBy('status')
                ->map(fn (Collection $group, $status) => [
                    'label' => $status ?: 'unknown',
                    'value' => $group->count(),
                ])
                ->sortByDesc('value')
                ->values()
                ->all(),
        ];
    }

    public function tradeProfitBreakdown(?Collection $trades, ?Collection $commodityNames = null): Collection
    {
        $commodityNames ??= DB::table('uex_commodities')->pluck('name', 'uex_id');

        return ($trades ?? collect())
            ->groupBy('commodity_uex_id')
            ->map(function (Collection $group, $commodityId) use ($commodityNames) {
                $label = $commodityNames[$commodityId] ?? ($commodityId ? "Commodity {$commodityId}" : 'Unknown Commodity');

                return [
                    'label' => $label,
                    'value' => round((float) $group->sum('profit'), 2),
                ];
            })
            ->sortByDesc('value')
            ->values();
    }

    public function groupSum(Collection $items, string $groupBy, string $valueKey = 'amount'): array
    {
        return $items
            ->groupBy(fn ($item) => $item->{$groupBy} ?: 'unclassified')
            ->map(fn (Collection $group, $label) => [
                'label' => (string) $label,
                'value' => round((float) $group->sum($valueKey), 2),
            ])
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    protected function sumTransactionsByType(Collection $transactions, string $type): float
    {
        return round((float) $transactions->where('type', $type)->sum('amount'), 2);
    }

    protected function sumBalance(Collection $transactions): float
    {
        $income = (float) $transactions->where('type', 'income')->sum('amount');
        $expense = (float) $transactions->where('type', 'expense')->sum('amount');
        $adjustments = (float) $transactions->where('type', 'adjustment')->sum('amount');

        return round($income - $expense + $adjustments, 2);
    }
}
