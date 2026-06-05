<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerActivityLog;
use App\Models\LedgerInventoryItem;
use App\Models\LedgerShipAsset;
use App\Models\LedgerTrade;
use App\Models\LedgerTransaction;
use App\Models\Squadron;
use App\Models\User;
use App\Models\WipeCycle;
use Illuminate\Support\Collection;

class LedgerReadModel
{
    public function __construct(
        protected LedgerCycleService $cycles,
        protected LedgerReferenceService $references,
        protected LedgerSummaryService $summaries,
        protected LedgerTransferService $transfers,
        protected LedgerFeatureService $features,
    ) {}

    public function buildPageData(User $user, string $wipeFilter = 'current'): array
    {
        $owner = $this->personalOwner($user);
        $currentWipe = $this->cycles->currentWipeCycle();
        $resolvedFilter = $this->cycles->resolveWipeFilter($wipeFilter, $currentWipe);
        $references = $this->references->referenceOptions();
        $referenceMaps = $this->references->referenceMaps();
        $inventorySuggestionMaps = $this->references->inventorySuggestionMaps($references);
        $shipPricingMap = $this->references->suggestionMap($references['shipPricing'] ?? []);
        $account = $owner->primaryAccount();

        [$transactions, $trades, $inventoryItems, $shipAssets] = $this->recentCollectionsForOwner($owner, $resolvedFilter, false);
        [$allTransactions, $allTrades, $allInventoryItems, $allShipAssets] = $this->allCollectionsForOwner($owner, $resolvedFilter);
        [$allUserTransactions, $allUserTrades, $allUserInventoryItems, $allUserShipAssets] = $this->allCollectionsForOwner($owner, [
            'mode' => 'all',
            'wipe' => null,
        ]);

        $overviewCards = $this->summaries->summarizeLedgerCollections(
            $allTransactions,
            $allTrades,
            $allInventoryItems,
            $allShipAssets,
            $inventorySuggestionMaps,
            $shipPricingMap
        );
        $topCommodityTrades = $this->summaries->tradeProfitBreakdown($allTrades)->take(5)->values()->all();
        $cycleSummaries = $this->summaries->buildCycleSummaries(
            $allUserTransactions,
            $allUserTrades,
            $allUserInventoryItems,
            $allUserShipAssets,
            $inventorySuggestionMaps,
            $shipPricingMap
        );
        $cycleComparison = $this->summaries->buildCycleComparison($cycleSummaries, $resolvedFilter, $currentWipe);

        return [
            'account' => [
                'id' => $account?->id,
                'name' => $account?->name ?? 'Personal Ledger',
                'type' => $account?->type ?? 'personal',
                'currency' => $account?->currency ?? 'aUEC',
            ],
            'transferTargets' => $this->transfers->transferTargetsFor($user, 'personal'),
            'transferInventoryOptions' => $this->references->transferInventoryOptions($allInventoryItems, $referenceMaps),
            'pendingFundTransfers' => $this->transfers->pendingTransferRequestsForContext($user, $this->transfers->personalInboxContext($user), 'funds', $referenceMaps),
            'pendingInventoryTransfers' => $this->transfers->pendingTransferRequestsForContext($user, $this->transfers->personalInboxContext($user), 'inventory', $referenceMaps),
            'recentFundTransfers' => $this->transfers->recentFundTransfersForContext($user, $this->transfers->personalInboxContext($user)),
            'wipeCycles' => $this->wipeCyclesPayload(),
            'activeWipeFilter' => $resolvedFilter['key'],
            'currentWipe' => $this->wipePayload($currentWipe),
            'selectedWipe' => $resolvedFilter['wipe'] ? $this->selectedWipePayload($resolvedFilter['wipe']) : null,
            'overview' => [
                'cards' => $overviewCards,
                'cycleSummaries' => $cycleSummaries->values()->all(),
                'cycleComparison' => $cycleComparison,
                'recentTransactions' => $transactions->map(fn (LedgerTransaction $transaction) => $this->references->presentTransaction($transaction, $referenceMaps))->all(),
                'recentTrades' => $trades->map(fn (LedgerTrade $trade) => $this->references->presentTrade($trade, $referenceMaps))->all(),
                'recentInventoryChanges' => $inventoryItems->map(
                    fn (LedgerInventoryItem $item) => $this->references->presentInventoryItem($item, $referenceMaps, $inventorySuggestionMaps)
                )->all(),
                'recentActivity' => $owner->scopeActivityLogs(LedgerActivityLog::query())
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get()
                    ->map(fn (LedgerActivityLog $log) => $this->references->presentActivityLog($log))
                    ->all(),
                'topCommodityTrades' => $topCommodityTrades,
            ],
            'transactions' => $transactions->map(fn (LedgerTransaction $transaction) => $this->references->presentTransaction($transaction, $referenceMaps))->all(),
            'trades' => $trades->map(fn (LedgerTrade $trade) => $this->references->presentTrade($trade, $referenceMaps))->all(),
            'inventoryItems' => $inventoryItems->map(
                fn (LedgerInventoryItem $item) => $this->references->presentInventoryItem($item, $referenceMaps, $inventorySuggestionMaps)
            )->all(),
            'shipAssets' => $shipAssets->map(
                fn (LedgerShipAsset $asset) => $this->references->presentShipAsset($asset, $referenceMaps, $shipPricingMap)
            )->all(),
            'reports' => $this->summaries->buildReportsForCollections(
                $allUserTransactions,
                $allUserTrades,
                $allUserInventoryItems,
                $allUserShipAssets,
                $resolvedFilter
            ),
            'references' => $references,
        ];
    }

    public function buildSquadronPageData(Squadron $squadron, string $wipeFilter = 'current', ?User $viewer = null): array
    {
        $owner = $this->squadronOwner($viewer ?? $squadron->leader ?? User::query()->findOrFail($squadron->leader_id), $squadron);
        $currentWipe = $this->cycles->currentWipeCycle();
        $resolvedFilter = $this->cycles->resolveWipeFilter($wipeFilter, $currentWipe);
        $references = $this->references->referenceOptions();
        $referenceMaps = $this->references->referenceMaps();
        $inventorySuggestionMaps = $this->references->inventorySuggestionMaps($references);
        $shipPricingMap = $this->references->suggestionMap($references['shipPricing'] ?? []);
        $account = $owner->primaryAccount();

        $activeMembers = $squadron->activeMembers()
            ->with('user.roles')
            ->orderBy('id')
            ->get();

        $memberLabels = $activeMembers
            ->mapWithKeys(fn ($member) => [
                (int) $member->user_id => $member->user?->rsi_handle
                    ?? $member->user?->display_name
                    ?? $member->user?->name
                    ?? "Member {$member->user_id}",
            ]);

        [$transactions, $trades, $inventoryItems, $shipAssets] = $this->recentCollectionsForOwner($owner, $resolvedFilter, true);
        [$allTransactions, $allTrades, $allInventoryItems, $allShipAssets] = $this->allCollectionsForOwner($owner, $resolvedFilter);

        $overviewCards = $this->summaries->summarizeLedgerCollections(
            $allTransactions,
            $allTrades,
            $allInventoryItems,
            $allShipAssets,
            $inventorySuggestionMaps,
            $shipPricingMap
        );
        $cycleSummaries = $this->summaries->buildCycleSummaries(
            $allTransactions,
            $allTrades,
            $allInventoryItems,
            $allShipAssets,
            $inventorySuggestionMaps,
            $shipPricingMap
        );
        $hasEntries = $allTransactions->isNotEmpty()
            || $allTrades->isNotEmpty()
            || $allInventoryItems->isNotEmpty()
            || $allShipAssets->isNotEmpty();
        $cycleComparison = $this->summaries->buildCycleComparison($cycleSummaries, $resolvedFilter, $currentWipe);

        return [
            'account' => [
                'id' => $account?->id,
                'name' => $account?->name ?? "{$squadron->name} Ledger",
                'type' => $account?->type ?? 'squadron',
                'currency' => $account?->currency ?? 'aUEC',
            ],
            'transferTargets' => $viewer ? $this->transfers->transferTargetsFor($viewer, 'squadron', $squadron) : [],
            'transferInventoryOptions' => $this->references->transferInventoryOptions($allInventoryItems, $referenceMaps),
            'pendingFundTransfers' => $viewer ? $this->transfers->pendingTransferRequestsForContext($viewer, $this->transfers->squadronInboxContext($viewer, $squadron), 'funds', $referenceMaps) : [],
            'pendingInventoryTransfers' => $viewer ? $this->transfers->pendingTransferRequestsForContext($viewer, $this->transfers->squadronInboxContext($viewer, $squadron), 'inventory', $referenceMaps) : [],
            'recentFundTransfers' => $viewer ? $this->transfers->recentFundTransfersForContext($viewer, $this->transfers->squadronInboxContext($viewer, $squadron)) : [],
            'hasEntries' => $hasEntries,
            'memberCount' => $activeMembers->count(),
            'members' => $activeMembers->map(fn ($member) => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $memberLabels->get((int) $member->user_id),
                'role' => $member->role ?? 'member',
            ])->values()->all(),
            'wipeCycles' => $this->wipeCyclesPayload(),
            'activeWipeFilter' => $resolvedFilter['key'],
            'currentWipe' => $this->wipePayload($currentWipe),
            'selectedWipe' => $resolvedFilter['wipe'] ? $this->selectedWipePayload($resolvedFilter['wipe']) : null,
            'overview' => [
                'cards' => $overviewCards,
                'cycleSummaries' => $cycleSummaries->values()->all(),
                'cycleComparison' => $cycleComparison,
                'recentInventoryChanges' => $inventoryItems
                    ->map(fn (LedgerInventoryItem $item) => $this->withLedgerOwner(
                        $this->references->presentInventoryItem($item, $referenceMaps, $inventorySuggestionMaps),
                        (int) $item->user_id,
                        $memberLabels
                    ))
                    ->all(),
                'recentShipAssets' => $shipAssets
                    ->map(fn (LedgerShipAsset $asset) => $this->withLedgerOwner(
                        $this->references->presentShipAsset($asset, $referenceMaps, $shipPricingMap),
                        (int) $asset->user_id,
                        $memberLabels
                    ))
                    ->all(),
                'recentTransactions' => $transactions
                    ->map(fn (LedgerTransaction $transaction) => $this->withLedgerOwner(
                        $this->references->presentTransaction($transaction, $referenceMaps),
                        (int) $transaction->user_id,
                        $memberLabels
                    ))
                    ->all(),
                'recentTrades' => $trades
                    ->map(fn (LedgerTrade $trade) => $this->withLedgerOwner(
                        $this->references->presentTrade($trade, $referenceMaps),
                        (int) $trade->user_id,
                        $memberLabels
                    ))
                    ->all(),
                'recentActivity' => $owner->scopeActivityLogs(LedgerActivityLog::query())
                    ->orderByDesc('created_at')
                    ->limit(8)
                    ->get()
                    ->map(fn (LedgerActivityLog $log) => $this->withLedgerOwner(
                        $this->references->presentActivityLog($log),
                        (int) ($log->subject_user_id ?: $log->actor_user_id),
                        $memberLabels
                    ))
                    ->all(),
                'topCommodityTrades' => $this->summaries->tradeProfitBreakdown($allTrades)->take(5)->values()->all(),
            ],
            'transactions' => $transactions
                ->map(fn (LedgerTransaction $transaction) => $this->withLedgerOwner(
                    $this->references->presentTransaction($transaction, $referenceMaps),
                    (int) $transaction->user_id,
                    $memberLabels
                ))
                ->all(),
            'trades' => $trades
                ->map(fn (LedgerTrade $trade) => $this->withLedgerOwner(
                    $this->references->presentTrade($trade, $referenceMaps),
                    (int) $trade->user_id,
                    $memberLabels
                ))
                ->all(),
            'inventoryItems' => $inventoryItems
                ->map(fn (LedgerInventoryItem $item) => $this->withLedgerOwner(
                    $this->references->presentInventoryItem($item, $referenceMaps, $inventorySuggestionMaps),
                    (int) $item->user_id,
                    $memberLabels
                ))
                ->all(),
            'shipAssets' => $shipAssets
                ->map(fn (LedgerShipAsset $asset) => $this->withLedgerOwner(
                    $this->references->presentShipAsset($asset, $referenceMaps, $shipPricingMap),
                    (int) $asset->user_id,
                    $memberLabels
                ))
                ->all(),
            'reports' => $this->summaries->buildReportsForCollections(
                $allTransactions,
                $allTrades,
                $allInventoryItems,
                $allShipAssets,
                $resolvedFilter
            ),
            'references' => $references,
        ];
    }

    public function buildOrganizationPageData(User $actor, string $wipeFilter = 'current'): array
    {
        $owner = $this->orgOwner($actor);
        $currentWipe = $this->cycles->currentWipeCycle();
        $resolvedFilter = $this->cycles->resolveWipeFilter($wipeFilter, $currentWipe);
        $references = $this->references->referenceOptions();
        $referenceMaps = $this->references->referenceMaps();
        $inventorySuggestionMaps = $this->references->inventorySuggestionMaps($references);
        $shipPricingMap = $this->references->suggestionMap($references['shipPricing'] ?? []);
        $account = $owner->primaryAccount();

        [$transactions, $trades, $inventoryItems, $shipAssets] = $this->recentCollectionsForOwner($owner, $resolvedFilter, true);
        [$allTransactions, $allTrades, $allInventoryItems, $allShipAssets] = $this->allCollectionsForOwner($owner, $resolvedFilter);

        $overviewCards = $this->summaries->summarizeLedgerCollections(
            $allTransactions,
            $allTrades,
            $allInventoryItems,
            $allShipAssets,
            $inventorySuggestionMaps,
            $shipPricingMap
        );
        $cycleSummaries = $this->summaries->buildCycleSummaries(
            $allTransactions,
            $allTrades,
            $allInventoryItems,
            $allShipAssets,
            $inventorySuggestionMaps,
            $shipPricingMap
        );
        $hasEntries = $allTransactions->isNotEmpty()
            || $allTrades->isNotEmpty()
            || $allInventoryItems->isNotEmpty()
            || $allShipAssets->isNotEmpty();
        $cycleComparison = $this->summaries->buildCycleComparison($cycleSummaries, $resolvedFilter, $currentWipe);

        return [
            'account' => [
                'id' => $account?->id,
                'name' => $account?->name ?? 'Horizon Treasury',
                'type' => $account?->type ?? 'organization',
                'currency' => $account?->currency ?? 'aUEC',
            ],
            'transferTargets' => $this->transfers->transferTargetsFor($actor, 'organization'),
            'transferInventoryOptions' => $this->references->transferInventoryOptions($allInventoryItems, $referenceMaps),
            'pendingFundTransfers' => $this->transfers->pendingTransferRequestsForContext($actor, $this->transfers->organizationInboxContext($actor), 'funds', $referenceMaps),
            'pendingInventoryTransfers' => $this->transfers->pendingTransferRequestsForContext($actor, $this->transfers->organizationInboxContext($actor), 'inventory', $referenceMaps),
            'recentFundTransfers' => $this->transfers->recentFundTransfersForContext($actor, $this->transfers->organizationInboxContext($actor)),
            'hasEntries' => $hasEntries,
            'wipeCycles' => $this->wipeCyclesPayload(),
            'activeWipeFilter' => $resolvedFilter['key'],
            'currentWipe' => $this->wipePayload($currentWipe),
            'selectedWipe' => $resolvedFilter['wipe'] ? $this->selectedWipePayload($resolvedFilter['wipe']) : null,
            'overview' => [
                'cards' => $overviewCards,
                'cycleSummaries' => $cycleSummaries->values()->all(),
                'cycleComparison' => $cycleComparison,
                'recentTransactions' => $transactions->map(fn (LedgerTransaction $transaction) => $this->references->presentTransaction($transaction, $referenceMaps))->all(),
                'recentTrades' => $trades->map(fn (LedgerTrade $trade) => $this->references->presentTrade($trade, $referenceMaps))->all(),
                'recentInventoryChanges' => $inventoryItems->map(
                    fn (LedgerInventoryItem $item) => $this->references->presentInventoryItem($item, $referenceMaps, $inventorySuggestionMaps)
                )->all(),
                'recentShipAssets' => $shipAssets->map(
                    fn (LedgerShipAsset $asset) => $this->references->presentShipAsset($asset, $referenceMaps, $shipPricingMap)
                )->all(),
                'recentActivity' => $owner->scopeActivityLogs(LedgerActivityLog::query())
                    ->orderByDesc('created_at')
                    ->limit(8)
                    ->get()
                    ->map(fn (LedgerActivityLog $log) => $this->references->presentActivityLog($log))
                    ->all(),
                'topCommodityTrades' => $this->summaries->tradeProfitBreakdown($allTrades)->take(5)->values()->all(),
            ],
            'transactions' => $transactions->map(fn (LedgerTransaction $transaction) => $this->references->presentTransaction($transaction, $referenceMaps))->all(),
            'trades' => $trades->map(fn (LedgerTrade $trade) => $this->references->presentTrade($trade, $referenceMaps))->all(),
            'inventoryItems' => $inventoryItems->map(
                fn (LedgerInventoryItem $item) => $this->references->presentInventoryItem($item, $referenceMaps, $inventorySuggestionMaps)
            )->all(),
            'shipAssets' => $shipAssets->map(
                fn (LedgerShipAsset $asset) => $this->references->presentShipAsset($asset, $referenceMaps, $shipPricingMap)
            )->all(),
            'reports' => $this->summaries->buildReportsForCollections(
                $allTransactions,
                $allTrades,
                $allInventoryItems,
                $allShipAssets,
                $resolvedFilter
            ),
            'references' => $references,
        ];
    }

    public function buildAdminData(): array
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
            'currentWipe' => $this->wipePayload($currentWipe),
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
                'ownership' => $this->buildAdminOwnershipSummaries(
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

    protected function buildAdminOwnershipSummaries(
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
                'account_count' => LedgerAccount::query()
                    ->whereNull('squadron_id')
                    ->where('is_org_owned', false)
                    ->count(),
                'transactions' => $transactions->filter(fn (LedgerTransaction $transaction) => blank($transaction->squadron_id) && ! $transaction->is_org_owned)->values(),
                'trades' => $trades->filter(fn (LedgerTrade $trade) => blank($trade->squadron_id) && ! $trade->is_org_owned)->values(),
                'inventory' => $inventoryItems->filter(fn (LedgerInventoryItem $item) => blank($item->squadron_id) && ! $item->is_org_owned)->values(),
                'ships' => $shipAssets->filter(fn (LedgerShipAsset $asset) => blank($asset->squadron_id) && ! $asset->is_org_owned)->values(),
            ],
            [
                'key' => 'squadron',
                'label' => 'Squadron Ledgers',
                'description' => 'Shared squadron books and pooled assets.',
                'account_count' => LedgerAccount::query()
                    ->whereNotNull('squadron_id')
                    ->where('is_org_owned', false)
                    ->count(),
                'transactions' => $transactions->filter(fn (LedgerTransaction $transaction) => filled($transaction->squadron_id) && ! $transaction->is_org_owned)->values(),
                'trades' => $trades->filter(fn (LedgerTrade $trade) => filled($trade->squadron_id) && ! $trade->is_org_owned)->values(),
                'inventory' => $inventoryItems->filter(fn (LedgerInventoryItem $item) => filled($item->squadron_id) && ! $item->is_org_owned)->values(),
                'ships' => $shipAssets->filter(fn (LedgerShipAsset $asset) => filled($asset->squadron_id) && ! $asset->is_org_owned)->values(),
            ],
            [
                'key' => 'organization',
                'label' => 'Org Treasury',
                'description' => 'Horizon-owned reserves and shared capital.',
                'account_count' => LedgerAccount::query()
                    ->whereNull('squadron_id')
                    ->where('is_org_owned', true)
                    ->count(),
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

    protected function recentCollectionsForOwner(LedgerOwner $owner, array $resolvedFilter, bool $includeUserRelations): array
    {
        $transactionRelations = ['account:id,name', 'shipAsset:id,custom_name,serial_or_label,vehicle_uex_id', 'operation:id,title'];
        $tradeRelations = ['account:id,name', 'shipAsset:id,custom_name,serial_or_label,vehicle_uex_id'];
        $inventoryRelations = ['assignedShipAsset:id,custom_name,serial_or_label,vehicle_uex_id', 'operation:id,title'];
        $shipRelations = [];

        if ($includeUserRelations) {
            $transactionRelations[] = 'user:id,name,rsi_handle,discord_name';
            $tradeRelations[] = 'user:id,name,rsi_handle,discord_name';
            $inventoryRelations[] = 'user:id,name,rsi_handle,discord_name';
            $shipRelations[] = 'user:id,name,rsi_handle,discord_name';
        }

        $transactions = $this->cycles->applyWipeFilter(
            $owner->scopeOwned(LedgerTransaction::query())
                ->with($transactionRelations)
                ->orderByDesc('transaction_date')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        $trades = $this->cycles->applyWipeFilter(
            $owner->scopeOwned(LedgerTrade::query())
                ->with($tradeRelations)
                ->orderByDesc('trade_date')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        $inventoryItems = $this->cycles->applyWipeFilter(
            $owner->scopeOwned(LedgerInventoryItem::query())
                ->with($inventoryRelations)
                ->orderByDesc('acquired_at')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        $shipAssets = $this->cycles->applyWipeFilter(
            $owner->scopeOwned(LedgerShipAsset::query())
                ->with($shipRelations)
                ->orderByDesc('acquired_at')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        return [$transactions, $trades, $inventoryItems, $shipAssets];
    }

    protected function allCollectionsForOwner(LedgerOwner $owner, array $resolvedFilter): array
    {
        $transactions = $this->cycles->applyWipeFilter($owner->scopeOwned(LedgerTransaction::query()), $resolvedFilter)->get();
        $trades = $this->cycles->applyWipeFilter($owner->scopeOwned(LedgerTrade::query()), $resolvedFilter)->get();
        $inventoryItems = $this->cycles->applyWipeFilter($owner->scopeOwned(LedgerInventoryItem::query()), $resolvedFilter)->get();
        $shipAssets = $this->cycles->applyWipeFilter($owner->scopeOwned(LedgerShipAsset::query()), $resolvedFilter)->get();

        return [$transactions, $trades, $inventoryItems, $shipAssets];
    }

    protected function wipeCyclesPayload(): array
    {
        return WipeCycle::query()
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
            ])
            ->values()
            ->all();
    }

    protected function wipePayload(WipeCycle $wipe): array
    {
        return [
            'id' => $wipe->id,
            'name' => $wipe->name,
            'star_citizen_version' => $wipe->star_citizen_version,
            'wipe_type' => $wipe->wipe_type,
            'started_at' => $wipe->started_at?->toIso8601String(),
            'ended_at' => $wipe->ended_at?->toIso8601String(),
        ];
    }

    protected function selectedWipePayload(WipeCycle $wipe): array
    {
        return [
            'id' => $wipe->id,
            'name' => $wipe->name,
            'star_citizen_version' => $wipe->star_citizen_version,
            'wipe_type' => $wipe->wipe_type,
            'is_current' => $wipe->is_current,
        ];
    }

    protected function withLedgerOwner(array $payload, int $userId, Collection $memberLabels): array
    {
        $payload['member_name'] = $memberLabels->get($userId, "Member {$userId}");

        return $payload;
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
