<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerInventoryItem;
use App\Models\LedgerShipAsset;
use App\Models\LedgerTrade;
use App\Models\LedgerTransaction;
use App\Models\WipeCycle;
use Illuminate\Support\Collection;

class LedgerAdminDataBuilder
{
    public function __construct(
        protected LedgerCycleService $cycles,
        protected LedgerFeatureService $features,
        protected LedgerReferenceService $references,
        protected LedgerSummaryService $summaries,
    ) {}

    public function build(): array
    {
        $currentWipe = $this->cycles->currentWipeCycle();
        $references = $this->references->referenceOptions();
        $inventorySuggestionMaps = $this->references->inventorySuggestionMaps($references);
        $shipPricingMap = $this->references->suggestionMap($references['shipPricing'] ?? []);

        $currentFilter = [
            'mode' => 'specific',
            'wipe' => $currentWipe,
        ];

        $allFilter = [
            'mode' => 'all',
            'wipe' => null,
        ];

        $currentTransactions = $this->cycles->applyWipeFilter(LedgerTransaction::query(), $currentFilter)->get();
        $currentTrades = $this->cycles->applyWipeFilter(LedgerTrade::query(), $currentFilter)->get();
        $currentInventoryItems = $this->cycles->applyWipeFilter(LedgerInventoryItem::query(), $currentFilter)->get();
        $currentShipAssets = $this->cycles->applyWipeFilter(LedgerShipAsset::query(), $currentFilter)->get();

        $allTransactions = LedgerTransaction::query()->get();
        $allTrades = LedgerTrade::query()->get();
        $allInventoryItems = LedgerInventoryItem::query()->get();
        $allShipAssets = LedgerShipAsset::query()->get();

        $currentSummary = $this->summaries->summarizeLedgerCollections(
            $currentTransactions,
            $currentTrades,
            $currentInventoryItems,
            $currentShipAssets,
            $inventorySuggestionMaps,
            $shipPricingMap
        );

        $currentReports = $this->summaries->buildReportsForCollections(
            $currentTransactions,
            $currentTrades,
            $currentInventoryItems,
            $currentShipAssets,
            $currentFilter
        );

        $allReports = $this->summaries->buildReportsForCollections(
            $allTransactions,
            $allTrades,
            $allInventoryItems,
            $allShipAssets,
            $allFilter
        );

        return [
            'feature' => [
                'enabled' => (bool) config('services.ledger.enabled', false),
                'preview_user_ids' => $this->features->previewUserIds(),
            ],
            'currentWipe' => [
                'id' => $currentWipe->id,
                'name' => $currentWipe->name,
                'star_citizen_version' => $currentWipe->star_citizen_version,
                'wipe_type' => $currentWipe->wipe_type,
                'started_at' => $currentWipe->started_at?->toIso8601String(),
                'ended_at' => $currentWipe->ended_at?->toIso8601String(),
            ],
            'counts' => [
                'accounts' => LedgerAccount::query()->count(),
                'transactions' => LedgerTransaction::query()->count(),
                'trades' => LedgerTrade::query()->count(),
                'inventory_items' => LedgerInventoryItem::query()->count(),
                'ship_assets' => LedgerShipAsset::query()->count(),
            ],
            'analytics' => [
                'snapshot' => [
                    'net_position' => $currentSummary['estimated_balance'] ?? 0,
                    'income' => $currentSummary['income'] ?? 0,
                    'expenses' => $currentSummary['expenses'] ?? 0,
                    'trade_profit' => $currentSummary['trade_profit'] ?? 0,
                    'inventory_value' => $currentSummary['inventory_value'] ?? 0,
                    'fleet_value' => $currentSummary['fleet_value'] ?? 0,
                    'transaction_count' => $currentSummary['transaction_count'] ?? 0,
                    'trade_count' => $currentSummary['trade_count'] ?? 0,
                    'inventory_count' => $currentSummary['inventory_count'] ?? 0,
                    'ship_count' => $currentSummary['ship_count'] ?? 0,
                ],
                'ownership' => $this->ownershipSummaries(
                    $currentTransactions,
                    $currentTrades,
                    $currentInventoryItems,
                    $currentShipAssets,
                    $inventorySuggestionMaps,
                    $shipPricingMap
                ),
                'reports' => [
                    'cycle_profit_loss' => $allReports['profitLossByWipe'] ?? [],
                    'income_by_source' => $currentReports['incomeBySource'] ?? [],
                    'expenses_by_source' => $currentReports['expensesBySource'] ?? [],
                    'trade_profit_by_commodity' => $currentReports['tradeProfitByCommodity'] ?? [],
                    'inventory_value_by_category' => $currentReports['inventoryValueByCategory'] ?? [],
                    'ship_summary_by_status' => $currentReports['shipSummaryByStatus'] ?? [],
                ],
            ],
            'wipeCycles' => WipeCycle::query()
                ->orderByDesc('is_current')
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (WipeCycle $wipe) => [
                    'id' => $wipe->id,
                    'name' => $wipe->name,
                    'star_citizen_version' => $wipe->star_citizen_version,
                    'wipe_type' => $wipe->wipe_type,
                    'is_current' => $wipe->is_current,
                    'started_at' => $wipe->started_at?->toIso8601String(),
                    'ended_at' => $wipe->ended_at?->toIso8601String(),
                    'notes' => $wipe->notes,
                ])
                ->values()
                ->all(),
        ];
    }

    protected function ownershipSummaries(
        Collection $transactions,
        Collection $trades,
        Collection $inventoryItems,
        Collection $shipAssets,
        array $inventorySuggestionMaps,
        Collection $shipPricingMap
    ): array {
        $ownershipScopes = [
            [
                'key' => 'personal',
                'label' => 'Personal Ledgers',
                'description' => 'Member-owned books and balances.',
                'account_count' => LedgerAccount::query()->whereNull('squadron_id')->where('is_org_owned', false)->count(),
                'transactions' => $transactions->filter(fn (LedgerTransaction $transaction) => blank($transaction->squadron_id) && ! $transaction->is_org_owned)->values(),
                'trades' => $trades->filter(fn (LedgerTrade $trade) => blank($trade->squadron_id) && ! $trade->is_org_owned)->values(),
                'inventory' => $inventoryItems->filter(fn (LedgerInventoryItem $item) => blank($item->squadron_id) && ! $item->is_org_owned)->values(),
                'ships' => $shipAssets->filter(fn (LedgerShipAsset $asset) => blank($asset->squadron_id) && ! $asset->is_org_owned)->values(),
            ],
            [
                'key' => 'squadron',
                'label' => 'Squadron Ledgers',
                'description' => 'Shared squadron books and pooled assets.',
                'account_count' => LedgerAccount::query()->whereNotNull('squadron_id')->where('is_org_owned', false)->count(),
                'transactions' => $transactions->filter(fn (LedgerTransaction $transaction) => filled($transaction->squadron_id) && ! $transaction->is_org_owned)->values(),
                'trades' => $trades->filter(fn (LedgerTrade $trade) => filled($trade->squadron_id) && ! $trade->is_org_owned)->values(),
                'inventory' => $inventoryItems->filter(fn (LedgerInventoryItem $item) => filled($item->squadron_id) && ! $item->is_org_owned)->values(),
                'ships' => $shipAssets->filter(fn (LedgerShipAsset $asset) => filled($asset->squadron_id) && ! $asset->is_org_owned)->values(),
            ],
            [
                'key' => 'organization',
                'label' => 'Org Treasury',
                'description' => 'Horizon-owned reserves and shared capital.',
                'account_count' => LedgerAccount::query()->whereNull('squadron_id')->where('is_org_owned', true)->count(),
                'transactions' => $transactions->filter(fn (LedgerTransaction $transaction) => blank($transaction->squadron_id) && $transaction->is_org_owned)->values(),
                'trades' => $trades->filter(fn (LedgerTrade $trade) => blank($trade->squadron_id) && $trade->is_org_owned)->values(),
                'inventory' => $inventoryItems->filter(fn (LedgerInventoryItem $item) => blank($item->squadron_id) && $item->is_org_owned)->values(),
                'ships' => $shipAssets->filter(fn (LedgerShipAsset $asset) => blank($asset->squadron_id) && $asset->is_org_owned)->values(),
            ],
        ];

        return collect($ownershipScopes)
            ->map(function (array $scope) use ($inventorySuggestionMaps, $shipPricingMap) {
                $cards = $this->summaries->summarizeLedgerCollections(
                    $scope['transactions'],
                    $scope['trades'],
                    $scope['inventory'],
                    $scope['ships'],
                    $inventorySuggestionMaps,
                    $shipPricingMap
                );

                return [
                    'key' => $scope['key'],
                    'label' => $scope['label'],
                    'description' => $scope['description'],
                    'account_count' => $scope['account_count'],
                    'record_count' => $scope['transactions']->count()
                        + $scope['trades']->count()
                        + $scope['inventory']->count()
                        + $scope['ships']->count(),
                    'cards' => [
                        'net_position' => $cards['estimated_balance'] ?? 0,
                        'trade_profit' => $cards['trade_profit'] ?? 0,
                        'inventory_value' => $cards['inventory_value'] ?? 0,
                        'fleet_value' => $cards['fleet_value'] ?? 0,
                    ],
                ];
            })
            ->values()
            ->all();
    }
}
