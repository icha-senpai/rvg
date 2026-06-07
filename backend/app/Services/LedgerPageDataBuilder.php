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
use Illuminate\Support\Collection;

class LedgerPageDataBuilder
{
    public function __construct(
        protected LedgerCycleService $cycles,
        protected LedgerReferenceService $references,
        protected LedgerSummaryService $summaries,
        protected LedgerTransferService $transfers,
    ) {}

    public function buildPersonal(User $user, string $wipeFilter = 'current'): array
    {
        $owner = $this->personalOwner($user);
        $state = $this->pageState($owner, $wipeFilter);

        [$transactions, $trades, $inventoryItems, $shipAssets] = $this->recentCollectionsForOwner($owner, $state['resolvedFilter'], false);
        [$allTransactions, $allTrades, $allInventoryItems, $allShipAssets] = $this->allCollectionsForOwner($owner, $state['resolvedFilter']);
        [$allUserTransactions, $allUserTrades, $allUserInventoryItems, $allUserShipAssets] = $this->allCollectionsForOwner($owner, [
            'mode' => 'all',
            'wipe' => null,
        ]);

        $overviewCards = $this->summaries->summarizeLedgerCollections(
            $allTransactions,
            $allTrades,
            $allInventoryItems,
            $allShipAssets,
            $state['inventorySuggestionMaps'],
            $state['shipPricingMap']
        );
        $cycleSummaries = $this->summaries->buildCycleSummaries(
            $allUserTransactions,
            $allUserTrades,
            $allUserInventoryItems,
            $allUserShipAssets,
            $state['inventorySuggestionMaps'],
            $state['shipPricingMap']
        );

        return array_merge($this->basePayload($state, $state['account']?->name ?? 'Personal Ledger', 'personal'), [
            'transferTargets' => $this->transfers->transferTargetsFor($user, 'personal'),
            'transferInventoryOptions' => $this->references->transferInventoryOptions($allInventoryItems, $state['referenceMaps']),
            'pendingFundTransfers' => $this->transfers->pendingTransferRequestsForContext($user, $this->transfers->personalInboxContext($user), 'funds', $state['referenceMaps']),
            'pendingInventoryTransfers' => $this->transfers->pendingTransferRequestsForContext($user, $this->transfers->personalInboxContext($user), 'inventory', $state['referenceMaps']),
            'recentFundTransfers' => $this->transfers->recentFundTransfersForContext($user, $this->transfers->personalInboxContext($user)),
            'overview' => [
                'cards' => $overviewCards,
                'cycleSummaries' => $cycleSummaries->values()->all(),
                'cycleComparison' => $this->summaries->buildCycleComparison($cycleSummaries, $state['resolvedFilter'], $state['currentWipe']),
                'recentTransactions' => $transactions->map(fn (LedgerTransaction $transaction) => $this->references->presentTransaction($transaction, $state['referenceMaps']))->all(),
                'recentTrades' => $trades->map(fn (LedgerTrade $trade) => $this->references->presentTrade($trade, $state['referenceMaps']))->all(),
                'recentInventoryChanges' => $inventoryItems->map(
                    fn (LedgerInventoryItem $item) => $this->references->presentInventoryItem($item, $state['referenceMaps'], $state['inventorySuggestionMaps'])
                )->all(),
                'recentActivity' => $this->recentActivityPayload($owner, 5),
                'topCommodityTrades' => $this->summaries->tradeProfitBreakdown($allTrades)->take(5)->values()->all(),
            ],
            'transactions' => $transactions->map(fn (LedgerTransaction $transaction) => $this->references->presentTransaction($transaction, $state['referenceMaps']))->all(),
            'trades' => $trades->map(fn (LedgerTrade $trade) => $this->references->presentTrade($trade, $state['referenceMaps']))->all(),
            'inventoryItems' => $inventoryItems->map(
                fn (LedgerInventoryItem $item) => $this->references->presentInventoryItem($item, $state['referenceMaps'], $state['inventorySuggestionMaps'])
            )->all(),
            'shipAssets' => $shipAssets->map(
                fn (LedgerShipAsset $asset) => $this->references->presentShipAsset($asset, $state['referenceMaps'], $state['shipPricingMap'])
            )->all(),
            'reports' => $this->summaries->buildReportsForCollections(
                $allUserTransactions,
                $allUserTrades,
                $allUserInventoryItems,
                $allUserShipAssets,
                $state['resolvedFilter']
            ),
        ]);
    }

    public function buildSquadron(Squadron $squadron, string $wipeFilter = 'current', ?User $viewer = null): array
    {
        $actor = $viewer ?? $squadron->leader ?? User::query()->findOrFail($squadron->leader_id);
        $owner = $this->squadronOwner($actor, $squadron);
        $state = $this->pageState($owner, $wipeFilter);
        $memberLabels = $this->memberLabelsForSquadron($squadron);
        $activeMembers = $squadron->activeMembers()
            ->with('user.roles')
            ->orderBy('id')
            ->get();

        [$transactions, $trades, $inventoryItems, $shipAssets] = $this->recentCollectionsForOwner($owner, $state['resolvedFilter'], true);
        [$allTransactions, $allTrades, $allInventoryItems, $allShipAssets] = $this->allCollectionsForOwner($owner, $state['resolvedFilter']);
        $overviewCards = $this->summaries->summarizeLedgerCollections(
            $allTransactions,
            $allTrades,
            $allInventoryItems,
            $allShipAssets,
            $state['inventorySuggestionMaps'],
            $state['shipPricingMap']
        );
        $cycleSummaries = $this->summaries->buildCycleSummaries(
            $allTransactions,
            $allTrades,
            $allInventoryItems,
            $allShipAssets,
            $state['inventorySuggestionMaps'],
            $state['shipPricingMap']
        );

        return array_merge($this->basePayload($state, $state['account']?->name ?? "{$squadron->name} Ledger", 'squadron'), [
            'transferTargets' => $viewer ? $this->transfers->transferTargetsFor($viewer, 'squadron', $squadron) : [],
            'transferInventoryOptions' => $this->references->transferInventoryOptions($allInventoryItems, $state['referenceMaps']),
            'pendingFundTransfers' => $viewer ? $this->transfers->pendingTransferRequestsForContext($viewer, $this->transfers->squadronInboxContext($viewer, $squadron), 'funds', $state['referenceMaps']) : [],
            'pendingInventoryTransfers' => $viewer ? $this->transfers->pendingTransferRequestsForContext($viewer, $this->transfers->squadronInboxContext($viewer, $squadron), 'inventory', $state['referenceMaps']) : [],
            'recentFundTransfers' => $viewer ? $this->transfers->recentFundTransfersForContext($viewer, $this->transfers->squadronInboxContext($viewer, $squadron)) : [],
            'hasEntries' => $this->hasEntries($allTransactions, $allTrades, $allInventoryItems, $allShipAssets),
            'memberCount' => $activeMembers->count(),
            'members' => $activeMembers->map(fn ($member) => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $memberLabels->get((int) $member->user_id),
                'role' => $member->role ?? 'member',
            ])->values()->all(),
            'overview' => [
                'cards' => $overviewCards,
                'cycleSummaries' => $cycleSummaries->values()->all(),
                'cycleComparison' => $this->summaries->buildCycleComparison($cycleSummaries, $state['resolvedFilter'], $state['currentWipe']),
                'recentInventoryChanges' => $inventoryItems->map(
                    fn (LedgerInventoryItem $item) => $this->withLedgerOwner(
                        $this->references->presentInventoryItem($item, $state['referenceMaps'], $state['inventorySuggestionMaps']),
                        (int) $item->user_id,
                        $memberLabels
                    )
                )->all(),
                'recentShipAssets' => $shipAssets->map(
                    fn (LedgerShipAsset $asset) => $this->withLedgerOwner(
                        $this->references->presentShipAsset($asset, $state['referenceMaps'], $state['shipPricingMap']),
                        (int) $asset->user_id,
                        $memberLabels
                    )
                )->all(),
                'recentTransactions' => $transactions->map(
                    fn (LedgerTransaction $transaction) => $this->withLedgerOwner(
                        $this->references->presentTransaction($transaction, $state['referenceMaps']),
                        (int) $transaction->user_id,
                        $memberLabels
                    )
                )->all(),
                'recentTrades' => $trades->map(
                    fn (LedgerTrade $trade) => $this->withLedgerOwner(
                        $this->references->presentTrade($trade, $state['referenceMaps']),
                        (int) $trade->user_id,
                        $memberLabels
                    )
                )->all(),
                'recentActivity' => $this->recentActivityPayload($owner, 8, $memberLabels),
                'topCommodityTrades' => $this->summaries->tradeProfitBreakdown($allTrades)->take(5)->values()->all(),
            ],
            'transactions' => $transactions->map(
                fn (LedgerTransaction $transaction) => $this->withLedgerOwner(
                    $this->references->presentTransaction($transaction, $state['referenceMaps']),
                    (int) $transaction->user_id,
                    $memberLabels
                )
            )->all(),
            'trades' => $trades->map(
                fn (LedgerTrade $trade) => $this->withLedgerOwner(
                    $this->references->presentTrade($trade, $state['referenceMaps']),
                    (int) $trade->user_id,
                    $memberLabels
                )
            )->all(),
            'inventoryItems' => $inventoryItems->map(
                fn (LedgerInventoryItem $item) => $this->withLedgerOwner(
                    $this->references->presentInventoryItem($item, $state['referenceMaps'], $state['inventorySuggestionMaps']),
                    (int) $item->user_id,
                    $memberLabels
                )
            )->all(),
            'shipAssets' => $shipAssets->map(
                fn (LedgerShipAsset $asset) => $this->withLedgerOwner(
                    $this->references->presentShipAsset($asset, $state['referenceMaps'], $state['shipPricingMap']),
                    (int) $asset->user_id,
                    $memberLabels
                )
            )->all(),
            'reports' => $this->summaries->buildReportsForCollections(
                $allTransactions,
                $allTrades,
                $allInventoryItems,
                $allShipAssets,
                $state['resolvedFilter']
            ),
        ]);
    }

    public function buildOrganization(User $actor, string $wipeFilter = 'current'): array
    {
        $owner = $this->orgOwner($actor);
        $state = $this->pageState($owner, $wipeFilter);

        [$transactions, $trades, $inventoryItems, $shipAssets] = $this->recentCollectionsForOwner($owner, $state['resolvedFilter'], true);
        [$allTransactions, $allTrades, $allInventoryItems, $allShipAssets] = $this->allCollectionsForOwner($owner, $state['resolvedFilter']);
        $overviewCards = $this->summaries->summarizeLedgerCollections(
            $allTransactions,
            $allTrades,
            $allInventoryItems,
            $allShipAssets,
            $state['inventorySuggestionMaps'],
            $state['shipPricingMap']
        );
        $cycleSummaries = $this->summaries->buildCycleSummaries(
            $allTransactions,
            $allTrades,
            $allInventoryItems,
            $allShipAssets,
            $state['inventorySuggestionMaps'],
            $state['shipPricingMap']
        );

        return array_merge($this->basePayload($state, $state['account']?->name ?? 'Horizon Treasury', 'organization'), [
            'transferTargets' => $this->transfers->transferTargetsFor($actor, 'organization'),
            'transferInventoryOptions' => $this->references->transferInventoryOptions($allInventoryItems, $state['referenceMaps']),
            'pendingFundTransfers' => $this->transfers->pendingTransferRequestsForContext($actor, $this->transfers->organizationInboxContext($actor), 'funds', $state['referenceMaps']),
            'pendingInventoryTransfers' => $this->transfers->pendingTransferRequestsForContext($actor, $this->transfers->organizationInboxContext($actor), 'inventory', $state['referenceMaps']),
            'recentFundTransfers' => $this->transfers->recentFundTransfersForContext($actor, $this->transfers->organizationInboxContext($actor)),
            'hasEntries' => $this->hasEntries($allTransactions, $allTrades, $allInventoryItems, $allShipAssets),
            'overview' => [
                'cards' => $overviewCards,
                'cycleSummaries' => $cycleSummaries->values()->all(),
                'cycleComparison' => $this->summaries->buildCycleComparison($cycleSummaries, $state['resolvedFilter'], $state['currentWipe']),
                'recentTransactions' => $transactions->map(fn (LedgerTransaction $transaction) => $this->references->presentTransaction($transaction, $state['referenceMaps']))->all(),
                'recentTrades' => $trades->map(fn (LedgerTrade $trade) => $this->references->presentTrade($trade, $state['referenceMaps']))->all(),
                'recentInventoryChanges' => $inventoryItems->map(
                    fn (LedgerInventoryItem $item) => $this->references->presentInventoryItem($item, $state['referenceMaps'], $state['inventorySuggestionMaps'])
                )->all(),
                'recentShipAssets' => $shipAssets->map(
                    fn (LedgerShipAsset $asset) => $this->references->presentShipAsset($asset, $state['referenceMaps'], $state['shipPricingMap'])
                )->all(),
                'recentActivity' => $this->recentActivityPayload($owner, 8),
                'topCommodityTrades' => $this->summaries->tradeProfitBreakdown($allTrades)->take(5)->values()->all(),
            ],
            'transactions' => $transactions->map(fn (LedgerTransaction $transaction) => $this->references->presentTransaction($transaction, $state['referenceMaps']))->all(),
            'trades' => $trades->map(fn (LedgerTrade $trade) => $this->references->presentTrade($trade, $state['referenceMaps']))->all(),
            'inventoryItems' => $inventoryItems->map(
                fn (LedgerInventoryItem $item) => $this->references->presentInventoryItem($item, $state['referenceMaps'], $state['inventorySuggestionMaps'])
            )->all(),
            'shipAssets' => $shipAssets->map(
                fn (LedgerShipAsset $asset) => $this->references->presentShipAsset($asset, $state['referenceMaps'], $state['shipPricingMap'])
            )->all(),
            'reports' => $this->summaries->buildReportsForCollections(
                $allTransactions,
                $allTrades,
                $allInventoryItems,
                $allShipAssets,
                $state['resolvedFilter']
            ),
        ]);
    }

    protected function pageState(LedgerOwner $owner, string $wipeFilter): array
    {
        $currentWipe = $this->cycles->currentWipeCycle();
        $resolvedFilter = $this->cycles->resolveWipeFilter($wipeFilter, $currentWipe);
        $references = $this->references->referenceOptions();

        return [
            'currentWipe' => $currentWipe,
            'resolvedFilter' => $resolvedFilter,
            'references' => $references,
            'referenceMaps' => $this->references->referenceMaps(),
            'inventorySuggestionMaps' => $this->references->inventorySuggestionMaps($references),
            'shipPricingMap' => $this->references->suggestionMap($references['shipPricing'] ?? []),
            'account' => $owner->primaryAccount(),
        ];
    }

    protected function basePayload(array $state, string $accountName, string $type): array
    {
        return [
            'account' => [
                'id' => $state['account']?->id,
                'name' => $accountName,
                'type' => $state['account']?->type ?? $type,
                'currency' => $state['account']?->currency ?? 'aUEC',
            ],
            'wipeCycles' => $this->wipeCyclesPayload(),
            'activeWipeFilter' => $state['resolvedFilter']['key'],
            'currentWipe' => $this->wipePayload($state['currentWipe']),
            'selectedWipe' => $state['resolvedFilter']['wipe'] ? $this->selectedWipePayload($state['resolvedFilter']['wipe']) : null,
            'references' => $state['references'],
        ];
    }

    protected function memberLabelsForSquadron(Squadron $squadron): Collection
    {
        return $squadron->activeMembers()
            ->with('user.roles')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($member) => [
                (int) $member->user_id => $member->user?->rsi_handle
                    ?? $member->user?->display_name
                    ?? $member->user?->name
                    ?? "Member {$member->user_id}",
            ]);
    }

    protected function recentActivityPayload(LedgerOwner $owner, int $limit, ?Collection $memberLabels = null): array
    {
        return $owner->scopeActivityLogs(LedgerActivityLog::query())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (LedgerActivityLog $log) use ($memberLabels) {
                $payload = $this->references->presentActivityLog($log);

                if (! $memberLabels) {
                    return $payload;
                }

                return $this->withLedgerOwner($payload, (int) ($log->subject_user_id ?: $log->actor_user_id), $memberLabels);
            })
            ->all();
    }

    protected function hasEntries(Collection $transactions, Collection $trades, Collection $inventoryItems, Collection $shipAssets): bool
    {
        return $transactions->isNotEmpty()
            || $trades->isNotEmpty()
            || $inventoryItems->isNotEmpty()
            || $shipAssets->isNotEmpty();
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
