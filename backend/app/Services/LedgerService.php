<?php

namespace App\Services;

use App\Domain\AccessControl\AccessService;
use App\Models\LedgerAccount;
use App\Models\LedgerActivityLog;
use App\Models\LedgerInventoryItem;
use App\Models\LedgerShipAsset;
use App\Models\LedgerTrade;
use App\Models\LedgerTransaction;
use App\Models\Squadron;
use App\Models\User;
use App\Models\WipeCycle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LedgerService
{
    public function __construct(
        protected LedgerCycleService $cycles,
        protected LedgerReferenceService $references,
        protected LedgerSummaryService $summaries,
    ) {}

    public function currentWipeCycle(): WipeCycle
    {
        return $this->cycles->currentWipeCycle();
    }

    public function defaultAccountFor(User $user): LedgerAccount
    {
        return $this->cycles->defaultAccountFor($user);
    }

    public function defaultSquadronAccountFor(Squadron $squadron, User $actor): LedgerAccount
    {
        return $this->cycles->defaultSquadronAccountFor($squadron, $actor);
    }

    public function defaultOrgAccountFor(User $actor): LedgerAccount
    {
        return $this->cycles->defaultOrgAccountFor($actor);
    }

    public function buildPageData(User $user, string $wipeFilter = 'current'): array
    {
        $currentWipe = $this->ensureCurrentWipeCycle();
        $account = $this->defaultAccountFor($user);
        $resolvedFilter = $this->resolveWipeFilter($wipeFilter, $currentWipe);
        $references = $this->referenceOptions();

        $transactions = $this->applyWipeFilter(
            LedgerTransaction::query()
                ->where('user_id', $user->id)
                ->whereNull('squadron_id')
                ->where('is_org_owned', false)
                ->with(['account:id,name', 'shipAsset:id,custom_name,serial_or_label,vehicle_uex_id'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        $trades = $this->applyWipeFilter(
            LedgerTrade::query()
                ->where('user_id', $user->id)
                ->whereNull('squadron_id')
                ->where('is_org_owned', false)
                ->with(['account:id,name', 'shipAsset:id,custom_name,serial_or_label,vehicle_uex_id'])
                ->orderByDesc('trade_date')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        $inventoryItems = $this->applyWipeFilter(
            LedgerInventoryItem::query()
                ->where('user_id', $user->id)
                ->whereNull('squadron_id')
                ->where('is_org_owned', false)
                ->with(['assignedShipAsset:id,custom_name,serial_or_label,vehicle_uex_id'])
                ->orderByDesc('acquired_at')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        $shipAssets = $this->applyWipeFilter(
            LedgerShipAsset::query()
                ->where('user_id', $user->id)
                ->whereNull('squadron_id')
                ->where('is_org_owned', false)
                ->orderByDesc('acquired_at')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        $allTransactions = $this->applyWipeFilter(
            LedgerTransaction::query()->where('user_id', $user->id)->whereNull('squadron_id')->where('is_org_owned', false),
            $resolvedFilter
        )->get();

        $allTrades = $this->applyWipeFilter(
            LedgerTrade::query()->where('user_id', $user->id)->whereNull('squadron_id')->where('is_org_owned', false),
            $resolvedFilter
        )->get();

        $allInventoryItems = $this->applyWipeFilter(
            LedgerInventoryItem::query()->where('user_id', $user->id)->whereNull('squadron_id')->where('is_org_owned', false),
            $resolvedFilter
        )->get();

        $allShipAssets = $this->applyWipeFilter(
            LedgerShipAsset::query()->where('user_id', $user->id)->whereNull('squadron_id')->where('is_org_owned', false),
            $resolvedFilter
        )->get();

        $allUserTransactions = LedgerTransaction::query()->where('user_id', $user->id)->whereNull('squadron_id')->where('is_org_owned', false)->get();
        $allUserTrades = LedgerTrade::query()->where('user_id', $user->id)->whereNull('squadron_id')->where('is_org_owned', false)->get();
        $allUserInventoryItems = LedgerInventoryItem::query()->where('user_id', $user->id)->whereNull('squadron_id')->where('is_org_owned', false)->get();
        $allUserShipAssets = LedgerShipAsset::query()->where('user_id', $user->id)->whereNull('squadron_id')->where('is_org_owned', false)->get();

        $referenceMaps = $this->referenceMaps();
        $inventorySuggestionMaps = $this->inventorySuggestionMaps($references);
        $shipPricingMap = $this->suggestionMap($references['shipPricing'] ?? []);
        $overviewCards = $this->summarizeLedgerCollections(
            $allTransactions,
            $allTrades,
            $allInventoryItems,
            $allShipAssets,
            $inventorySuggestionMaps,
            $shipPricingMap
        );
        $topCommodityTrades = $this->tradeProfitBreakdown($allTrades)->take(5)->values()->all();
        $cycleSummaries = $this->buildCycleSummaries(
            $allUserTransactions,
            $allUserTrades,
            $allUserInventoryItems,
            $allUserShipAssets,
            $inventorySuggestionMaps,
            $shipPricingMap
        );
        $cycleComparison = $this->buildCycleComparison($cycleSummaries, $resolvedFilter, $currentWipe);

        return [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'currency' => $account->currency,
            ],
            'transferTargets' => $this->transferTargetsFor($user, 'personal'),
            'transferInventoryOptions' => $this->transferInventoryOptions($allUserInventoryItems, $referenceMaps),
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
                ])
                ->values()
                ->all(),
            'activeWipeFilter' => $resolvedFilter['key'],
            'currentWipe' => [
                'id' => $currentWipe->id,
                'name' => $currentWipe->name,
                'star_citizen_version' => $currentWipe->star_citizen_version,
                'wipe_type' => $currentWipe->wipe_type,
                'started_at' => $currentWipe->started_at?->toIso8601String(),
                'ended_at' => $currentWipe->ended_at?->toIso8601String(),
            ],
            'selectedWipe' => $resolvedFilter['wipe'] ? [
                'id' => $resolvedFilter['wipe']->id,
                'name' => $resolvedFilter['wipe']->name,
                'star_citizen_version' => $resolvedFilter['wipe']->star_citizen_version,
                'wipe_type' => $resolvedFilter['wipe']->wipe_type,
                'is_current' => $resolvedFilter['wipe']->is_current,
            ] : null,
            'overview' => [
                'cards' => $overviewCards,
                'cycleSummaries' => $cycleSummaries->values()->all(),
                'cycleComparison' => $cycleComparison,
                'recentTransactions' => $transactions->map(fn (LedgerTransaction $transaction) => $this->presentTransaction($transaction, $referenceMaps))->all(),
                'recentTrades' => $trades->map(fn (LedgerTrade $trade) => $this->presentTrade($trade, $referenceMaps))->all(),
                'recentInventoryChanges' => $inventoryItems->map(
                    fn (LedgerInventoryItem $item) => $this->presentInventoryItem($item, $referenceMaps, $inventorySuggestionMaps)
                )->all(),
                'recentActivity' => LedgerActivityLog::query()
                    ->where('subject_user_id', $user->id)
                    ->whereNull('squadron_id')
                    ->where('is_org_owned', false)
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get()
                    ->map(fn (LedgerActivityLog $log) => $this->presentActivityLog($log))
                    ->all(),
                'topCommodityTrades' => $topCommodityTrades,
            ],
            'transactions' => $transactions->map(fn (LedgerTransaction $transaction) => $this->presentTransaction($transaction, $referenceMaps))->all(),
            'trades' => $trades->map(fn (LedgerTrade $trade) => $this->presentTrade($trade, $referenceMaps))->all(),
            'inventoryItems' => $inventoryItems->map(
                fn (LedgerInventoryItem $item) => $this->presentInventoryItem($item, $referenceMaps, $inventorySuggestionMaps)
            )->all(),
            'shipAssets' => $shipAssets->map(
                fn (LedgerShipAsset $asset) => $this->presentShipAsset($asset, $referenceMaps, $shipPricingMap)
            )->all(),
            'reports' => $this->buildReportsForCollections(
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
        $currentWipe = $this->ensureCurrentWipeCycle();
        $resolvedFilter = $this->resolveWipeFilter($wipeFilter, $currentWipe);
        $references = $this->referenceOptions();
        $referenceMaps = $this->referenceMaps();
        $inventorySuggestionMaps = $this->inventorySuggestionMaps($references);
        $shipPricingMap = $this->suggestionMap($references['shipPricing'] ?? []);
        $account = LedgerAccount::query()
            ->where('squadron_id', $squadron->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

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

        $transactions = $this->applyWipeFilter(
            LedgerTransaction::query()
                ->where('squadron_id', $squadron->id)
                ->where('is_org_owned', false)
                ->with(['account:id,name', 'shipAsset:id,custom_name,serial_or_label,vehicle_uex_id', 'user:id,name,rsi_handle,discord_name'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        $trades = $this->applyWipeFilter(
            LedgerTrade::query()
                ->where('squadron_id', $squadron->id)
                ->where('is_org_owned', false)
                ->with(['account:id,name', 'shipAsset:id,custom_name,serial_or_label,vehicle_uex_id', 'user:id,name,rsi_handle,discord_name'])
                ->orderByDesc('trade_date')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        $inventoryItems = $this->applyWipeFilter(
            LedgerInventoryItem::query()
                ->where('squadron_id', $squadron->id)
                ->where('is_org_owned', false)
                ->with(['assignedShipAsset:id,custom_name,serial_or_label,vehicle_uex_id', 'user:id,name,rsi_handle,discord_name'])
                ->orderByDesc('acquired_at')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        $shipAssets = $this->applyWipeFilter(
            LedgerShipAsset::query()
                ->where('squadron_id', $squadron->id)
                ->where('is_org_owned', false)
                ->with(['user:id,name,rsi_handle,discord_name'])
                ->orderByDesc('acquired_at')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        $allTransactions = $this->applyWipeFilter(
            LedgerTransaction::query()->where('squadron_id', $squadron->id)->where('is_org_owned', false),
            $resolvedFilter
        )->get();

        $allTrades = $this->applyWipeFilter(
            LedgerTrade::query()->where('squadron_id', $squadron->id)->where('is_org_owned', false),
            $resolvedFilter
        )->get();

        $allInventoryItems = $this->applyWipeFilter(
            LedgerInventoryItem::query()->where('squadron_id', $squadron->id)->where('is_org_owned', false),
            $resolvedFilter
        )->get();

        $allShipAssets = $this->applyWipeFilter(
            LedgerShipAsset::query()->where('squadron_id', $squadron->id)->where('is_org_owned', false),
            $resolvedFilter
        )->get();

        $overviewCards = $this->summarizeLedgerCollections(
            $allTransactions,
            $allTrades,
            $allInventoryItems,
            $allShipAssets,
            $inventorySuggestionMaps,
            $shipPricingMap
        );
        $cycleSummaries = $this->buildCycleSummaries(
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
        $cycleComparison = $this->buildCycleComparison($cycleSummaries, $resolvedFilter, $currentWipe);

        return [
            'account' => [
                'id' => $account?->id,
                'name' => $account?->name ?? "{$squadron->name} Ledger",
                'type' => $account?->type ?? 'squadron',
                'currency' => $account?->currency ?? 'aUEC',
            ],
            'transferTargets' => $viewer ? $this->transferTargetsFor($viewer, 'squadron', $squadron) : [],
            'transferInventoryOptions' => $this->transferInventoryOptions($allInventoryItems, $referenceMaps),
            'hasEntries' => $hasEntries,
            'memberCount' => $activeMembers->count(),
            'members' => $activeMembers->map(fn ($member) => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $memberLabels->get((int) $member->user_id),
                'role' => $member->role ?? 'member',
            ])->values()->all(),
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
                ])
                ->values()
                ->all(),
            'activeWipeFilter' => $resolvedFilter['key'],
            'currentWipe' => [
                'id' => $currentWipe->id,
                'name' => $currentWipe->name,
                'star_citizen_version' => $currentWipe->star_citizen_version,
                'wipe_type' => $currentWipe->wipe_type,
                'started_at' => $currentWipe->started_at?->toIso8601String(),
                'ended_at' => $currentWipe->ended_at?->toIso8601String(),
            ],
            'selectedWipe' => $resolvedFilter['wipe'] ? [
                'id' => $resolvedFilter['wipe']->id,
                'name' => $resolvedFilter['wipe']->name,
                'star_citizen_version' => $resolvedFilter['wipe']->star_citizen_version,
                'wipe_type' => $resolvedFilter['wipe']->wipe_type,
                'is_current' => $resolvedFilter['wipe']->is_current,
            ] : null,
            'overview' => [
                'cards' => $overviewCards,
                'cycleSummaries' => $cycleSummaries->values()->all(),
                'cycleComparison' => $cycleComparison,
                'recentInventoryChanges' => $inventoryItems
                    ->map(fn (LedgerInventoryItem $item) => $this->withLedgerOwner(
                        $this->presentInventoryItem($item, $referenceMaps, $inventorySuggestionMaps),
                        (int) $item->user_id,
                        $memberLabels
                    ))
                    ->all(),
                'recentShipAssets' => $shipAssets
                    ->map(fn (LedgerShipAsset $asset) => $this->withLedgerOwner(
                        $this->presentShipAsset($asset, $referenceMaps, $shipPricingMap),
                        (int) $asset->user_id,
                        $memberLabels
                    ))
                    ->all(),
                'recentTransactions' => $transactions
                    ->map(fn (LedgerTransaction $transaction) => $this->withLedgerOwner(
                        $this->presentTransaction($transaction, $referenceMaps),
                        (int) $transaction->user_id,
                        $memberLabels
                    ))
                    ->all(),
                'recentTrades' => $trades
                    ->map(fn (LedgerTrade $trade) => $this->withLedgerOwner(
                        $this->presentTrade($trade, $referenceMaps),
                        (int) $trade->user_id,
                        $memberLabels
                    ))
                    ->all(),
                'recentActivity' => LedgerActivityLog::query()
                    ->where('squadron_id', $squadron->id)
                    ->where('is_org_owned', false)
                    ->orderByDesc('created_at')
                    ->limit(8)
                    ->get()
                    ->map(fn (LedgerActivityLog $log) => $this->withLedgerOwner(
                        $this->presentActivityLog($log),
                        (int) ($log->subject_user_id ?: $log->actor_user_id),
                        $memberLabels
                    ))
                    ->all(),
                'topCommodityTrades' => $this->tradeProfitBreakdown($allTrades)->take(5)->values()->all(),
            ],
            'transactions' => $transactions
                ->map(fn (LedgerTransaction $transaction) => $this->withLedgerOwner(
                    $this->presentTransaction($transaction, $referenceMaps),
                    (int) $transaction->user_id,
                    $memberLabels
                ))
                ->all(),
            'trades' => $trades
                ->map(fn (LedgerTrade $trade) => $this->withLedgerOwner(
                    $this->presentTrade($trade, $referenceMaps),
                    (int) $trade->user_id,
                    $memberLabels
                ))
                ->all(),
            'inventoryItems' => $inventoryItems
                ->map(fn (LedgerInventoryItem $item) => $this->withLedgerOwner(
                    $this->presentInventoryItem($item, $referenceMaps, $inventorySuggestionMaps),
                    (int) $item->user_id,
                    $memberLabels
                ))
                ->all(),
            'shipAssets' => $shipAssets
                ->map(fn (LedgerShipAsset $asset) => $this->withLedgerOwner(
                    $this->presentShipAsset($asset, $referenceMaps, $shipPricingMap),
                    (int) $asset->user_id,
                    $memberLabels
                ))
                ->all(),
            'reports' => $this->buildReportsForCollections(
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
        $currentWipe = $this->ensureCurrentWipeCycle();
        $resolvedFilter = $this->resolveWipeFilter($wipeFilter, $currentWipe);
        $references = $this->referenceOptions();
        $referenceMaps = $this->referenceMaps();
        $inventorySuggestionMaps = $this->inventorySuggestionMaps($references);
        $shipPricingMap = $this->suggestionMap($references['shipPricing'] ?? []);
        $account = LedgerAccount::query()
            ->where('is_org_owned', true)
            ->whereNull('squadron_id')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        $transactions = $this->applyWipeFilter(
            LedgerTransaction::query()
                ->where('is_org_owned', true)
                ->whereNull('squadron_id')
                ->with(['account:id,name', 'shipAsset:id,custom_name,serial_or_label,vehicle_uex_id', 'user:id,name,rsi_handle,discord_name'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        $trades = $this->applyWipeFilter(
            LedgerTrade::query()
                ->where('is_org_owned', true)
                ->whereNull('squadron_id')
                ->with(['account:id,name', 'shipAsset:id,custom_name,serial_or_label,vehicle_uex_id', 'user:id,name,rsi_handle,discord_name'])
                ->orderByDesc('trade_date')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        $inventoryItems = $this->applyWipeFilter(
            LedgerInventoryItem::query()
                ->where('is_org_owned', true)
                ->whereNull('squadron_id')
                ->with(['assignedShipAsset:id,custom_name,serial_or_label,vehicle_uex_id', 'user:id,name,rsi_handle,discord_name'])
                ->orderByDesc('acquired_at')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        $shipAssets = $this->applyWipeFilter(
            LedgerShipAsset::query()
                ->where('is_org_owned', true)
                ->whereNull('squadron_id')
                ->with(['user:id,name,rsi_handle,discord_name'])
                ->orderByDesc('acquired_at')
                ->orderByDesc('id'),
            $resolvedFilter
        )->limit(5)->get();

        $allTransactions = $this->applyWipeFilter(
            LedgerTransaction::query()->where('is_org_owned', true)->whereNull('squadron_id'),
            $resolvedFilter
        )->get();

        $allTrades = $this->applyWipeFilter(
            LedgerTrade::query()->where('is_org_owned', true)->whereNull('squadron_id'),
            $resolvedFilter
        )->get();

        $allInventoryItems = $this->applyWipeFilter(
            LedgerInventoryItem::query()->where('is_org_owned', true)->whereNull('squadron_id'),
            $resolvedFilter
        )->get();

        $allShipAssets = $this->applyWipeFilter(
            LedgerShipAsset::query()->where('is_org_owned', true)->whereNull('squadron_id'),
            $resolvedFilter
        )->get();

        $overviewCards = $this->summarizeLedgerCollections(
            $allTransactions,
            $allTrades,
            $allInventoryItems,
            $allShipAssets,
            $inventorySuggestionMaps,
            $shipPricingMap
        );
        $cycleSummaries = $this->buildCycleSummaries(
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
        $cycleComparison = $this->buildCycleComparison($cycleSummaries, $resolvedFilter, $currentWipe);

        return [
            'account' => [
                'id' => $account?->id,
                'name' => $account?->name ?? 'Horizon Treasury',
                'type' => $account?->type ?? 'organization',
                'currency' => $account?->currency ?? 'aUEC',
            ],
            'transferTargets' => $this->transferTargetsFor($actor, 'organization'),
            'transferInventoryOptions' => $this->transferInventoryOptions($allInventoryItems, $referenceMaps),
            'hasEntries' => $hasEntries,
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
                ])
                ->values()
                ->all(),
            'activeWipeFilter' => $resolvedFilter['key'],
            'currentWipe' => [
                'id' => $currentWipe->id,
                'name' => $currentWipe->name,
                'star_citizen_version' => $currentWipe->star_citizen_version,
                'wipe_type' => $currentWipe->wipe_type,
                'started_at' => $currentWipe->started_at?->toIso8601String(),
                'ended_at' => $currentWipe->ended_at?->toIso8601String(),
            ],
            'selectedWipe' => $resolvedFilter['wipe'] ? [
                'id' => $resolvedFilter['wipe']->id,
                'name' => $resolvedFilter['wipe']->name,
                'star_citizen_version' => $resolvedFilter['wipe']->star_citizen_version,
                'wipe_type' => $resolvedFilter['wipe']->wipe_type,
                'is_current' => $resolvedFilter['wipe']->is_current,
            ] : null,
            'overview' => [
                'cards' => $overviewCards,
                'cycleSummaries' => $cycleSummaries->values()->all(),
                'cycleComparison' => $cycleComparison,
                'recentTransactions' => $transactions->map(fn (LedgerTransaction $transaction) => $this->presentTransaction($transaction, $referenceMaps))->all(),
                'recentTrades' => $trades->map(fn (LedgerTrade $trade) => $this->presentTrade($trade, $referenceMaps))->all(),
                'recentInventoryChanges' => $inventoryItems->map(
                    fn (LedgerInventoryItem $item) => $this->presentInventoryItem($item, $referenceMaps, $inventorySuggestionMaps)
                )->all(),
                'recentShipAssets' => $shipAssets->map(
                    fn (LedgerShipAsset $asset) => $this->presentShipAsset($asset, $referenceMaps, $shipPricingMap)
                )->all(),
                'recentActivity' => LedgerActivityLog::query()
                    ->where('is_org_owned', true)
                    ->whereNull('squadron_id')
                    ->orderByDesc('created_at')
                    ->limit(8)
                    ->get()
                    ->map(fn (LedgerActivityLog $log) => $this->presentActivityLog($log))
                    ->all(),
                'topCommodityTrades' => $this->tradeProfitBreakdown($allTrades)->take(5)->values()->all(),
            ],
            'transactions' => $transactions->map(fn (LedgerTransaction $transaction) => $this->presentTransaction($transaction, $referenceMaps))->all(),
            'trades' => $trades->map(fn (LedgerTrade $trade) => $this->presentTrade($trade, $referenceMaps))->all(),
            'inventoryItems' => $inventoryItems->map(
                fn (LedgerInventoryItem $item) => $this->presentInventoryItem($item, $referenceMaps, $inventorySuggestionMaps)
            )->all(),
            'shipAssets' => $shipAssets->map(
                fn (LedgerShipAsset $asset) => $this->presentShipAsset($asset, $referenceMaps, $shipPricingMap)
            )->all(),
            'reports' => $this->buildReportsForCollections(
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
        $currentWipe = $this->ensureCurrentWipeCycle();

        return [
            'feature' => [
                'enabled' => (bool) config('services.ledger.enabled', false),
                'preview_user_ids' => app(LedgerFeatureService::class)->previewUserIds(),
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

    public function createTransaction(User $actor, User $owner, array $data): LedgerTransaction
    {
        return DB::transaction(function () use ($actor, $owner, $data) {
            $account = $this->resolveAccount($owner, $data['ledger_account_id'] ?? null);
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $amount = round((float) $data['amount'], 2);

            if ($data['type'] === 'adjustment') {
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

            if ($data['type'] !== 'adjustment' && $amount < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Only manual adjustments may be negative.',
                ]);
            }

            $transaction = LedgerTransaction::query()->create([
                'user_id' => $owner->id,
                'squadron_id' => null,
                'is_org_owned' => false,
                'ledger_account_id' => $account->id,
                'wipe_cycle_id' => $wipeCycle->id,
                'type' => $data['type'],
                'amount' => $amount,
                'currency' => $data['currency'] ?? $account->currency ?? 'aUEC',
                'source_type' => $data['source_type'] ?? null,
                'description' => $data['description'],
                'transaction_date' => $data['transaction_date'] ?? now(),
                'related_ship_asset_id' => $this->resolveShipAssetId($owner, $data['related_ship_asset_id'] ?? null),
                'related_operation_id' => $data['related_operation_id'] ?? null,
                'related_uex_type' => $data['related_uex_type'] ?? null,
                'related_uex_id' => $data['related_uex_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logActivity($actor, $owner, $wipeCycle, 'transaction.created', $transaction, [
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'description' => $transaction->description,
            ]);

            return $transaction;
        });
    }

    public function createTrade(User $actor, User $owner, array $data): LedgerTrade
    {
        return DB::transaction(function () use ($actor, $owner, $data) {
            $account = $this->resolveAccount($owner, $data['ledger_account_id'] ?? null);
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $quantity = round((float) $data['quantity'], 4);
            $buyPrice = round((float) $data['buy_price_per_unit'], 2);
            $sellPrice = round((float) $data['sell_price_per_unit'], 2);

            if ($quantity <= 0 || $buyPrice < 0 || $sellPrice < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Trade quantities and prices must be valid positive values.',
                ]);
            }

            $totalCost = round($quantity * $buyPrice, 2);
            $totalRevenue = round($quantity * $sellPrice, 2);
            $profit = round($totalRevenue - $totalCost, 2);
            $profitPerUnit = $quantity > 0 ? round($profit / $quantity, 2) : 0.0;

            $trade = LedgerTrade::query()->create([
                'user_id' => $owner->id,
                'squadron_id' => null,
                'is_org_owned' => false,
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
                'ship_asset_id' => $this->resolveShipAssetId($owner, $data['ship_asset_id'] ?? null),
                'cargo_capacity_used' => $data['cargo_capacity_used'] ?? null,
                'trade_date' => $data['trade_date'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logActivity($actor, $owner, $wipeCycle, 'trade.created', $trade, [
                'quantity' => $trade->quantity,
                'profit' => $trade->profit,
            ]);

            return $trade;
        });
    }

    public function createInventoryItem(User $actor, User $owner, array $data): LedgerInventoryItem
    {
        return DB::transaction(function () use ($actor, $owner, $data) {
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $sourceType = $data['source_type'];
            $referenceId = $data['uex_reference_id'] ?? null;

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

            $item = LedgerInventoryItem::query()->create([
                'user_id' => $owner->id,
                'squadron_id' => null,
                'is_org_owned' => false,
                'wipe_cycle_id' => $wipeCycle->id,
                'source_type' => $sourceType,
                'uex_reference_type' => $data['uex_reference_type'] ?? $sourceType,
                'uex_reference_id' => $referenceId,
                'custom_name' => $data['custom_name'] ?? null,
                'category' => $data['category'] ?? null,
                'quantity' => $data['quantity'] ?? 1,
                'unit_label' => $data['unit_label'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'terminal_uex_id' => $data['terminal_uex_id'] ?? null,
                'assigned_ship_asset_id' => $this->resolveShipAssetId($owner, $data['assigned_ship_asset_id'] ?? null),
                'purchase_price' => $data['purchase_price'] ?? null,
                'estimated_value' => $data['estimated_value'] ?? null,
                'currency' => $data['currency'] ?? 'aUEC',
                'status' => $data['status'] ?? 'owned',
                'acquired_at' => $data['acquired_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logActivity($actor, $owner, $wipeCycle, 'inventory.created', $item, [
                'source_type' => $item->source_type,
                'quantity' => $item->quantity,
            ]);

            return $item;
        });
    }

    public function createShipAsset(User $actor, User $owner, array $data): LedgerShipAsset
    {
        return DB::transaction(function () use ($actor, $owner, $data) {
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);

            $asset = LedgerShipAsset::query()->create([
                'user_id' => $owner->id,
                'squadron_id' => null,
                'is_org_owned' => false,
                'wipe_cycle_id' => $wipeCycle->id,
                'vehicle_uex_id' => $data['vehicle_uex_id'] ?? null,
                'custom_name' => $data['custom_name'] ?? null,
                'serial_or_label' => $data['serial_or_label'] ?? null,
                'purchase_price' => $data['purchase_price'] ?? null,
                'currency' => $data['currency'] ?? 'aUEC',
                'acquisition_source' => $data['acquisition_source'] ?? null,
                'current_location' => $data['current_location'] ?? null,
                'status' => $data['status'] ?? 'owned',
                'acquired_at' => $data['acquired_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logActivity($actor, $owner, $wipeCycle, 'ship_asset.created', $asset, [
                'vehicle_uex_id' => $asset->vehicle_uex_id,
                'status' => $asset->status,
            ]);

            return $asset;
        });
    }

    public function updateTransaction(User $actor, User $owner, LedgerTransaction $transaction, array $data): LedgerTransaction
    {
        $this->ensureOwnedRecord($owner, $transaction);
        $this->ensureTransactionIsManuallyEditable($transaction);

        return DB::transaction(function () use ($actor, $owner, $transaction, $data) {
            $account = $this->resolveAccount($owner, $data['ledger_account_id'] ?? null);
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $amount = round((float) $data['amount'], 2);

            if ($data['type'] === 'adjustment') {
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

            if ($data['type'] !== 'adjustment' && $amount < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Only manual adjustments may be negative.',
                ]);
            }

            $transaction->forceFill([
                'ledger_account_id' => $account->id,
                'wipe_cycle_id' => $wipeCycle->id,
                'type' => $data['type'],
                'amount' => $amount,
                'currency' => $data['currency'] ?? $account->currency ?? 'aUEC',
                'source_type' => $data['source_type'] ?? null,
                'description' => $data['description'],
                'transaction_date' => $data['transaction_date'] ?? now(),
                'related_ship_asset_id' => $this->resolveShipAssetId($owner, $data['related_ship_asset_id'] ?? null),
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

    public function deleteTransaction(User $actor, User $owner, LedgerTransaction $transaction): void
    {
        $this->ensureOwnedRecord($owner, $transaction);
        $this->ensureTransactionIsManuallyEditable($transaction);

        $this->logActivity($actor, $owner, $transaction->wipeCycle, 'transaction.deleted', $transaction, [
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'description' => $transaction->description,
        ]);

        $transaction->delete();
    }

    public function updateTrade(User $actor, User $owner, LedgerTrade $trade, array $data): LedgerTrade
    {
        $this->ensureOwnedRecord($owner, $trade);

        return DB::transaction(function () use ($actor, $owner, $trade, $data) {
            $account = $this->resolveAccount($owner, $data['ledger_account_id'] ?? null);
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $quantity = round((float) $data['quantity'], 4);
            $buyPrice = round((float) $data['buy_price_per_unit'], 2);
            $sellPrice = round((float) $data['sell_price_per_unit'], 2);

            if ($quantity <= 0 || $buyPrice < 0 || $sellPrice < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Trade quantities and prices must be valid positive values.',
                ]);
            }

            $totalCost = round($quantity * $buyPrice, 2);
            $totalRevenue = round($quantity * $sellPrice, 2);
            $profit = round($totalRevenue - $totalCost, 2);
            $profitPerUnit = $quantity > 0 ? round($profit / $quantity, 2) : 0.0;

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
                'ship_asset_id' => $this->resolveShipAssetId($owner, $data['ship_asset_id'] ?? null),
                'cargo_capacity_used' => $data['cargo_capacity_used'] ?? null,
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

    public function deleteTrade(User $actor, User $owner, LedgerTrade $trade): void
    {
        $this->ensureOwnedRecord($owner, $trade);

        $this->logActivity($actor, $owner, $trade->wipeCycle, 'trade.deleted', $trade, [
            'quantity' => $trade->quantity,
            'profit' => $trade->profit,
        ]);

        $trade->delete();
    }

    public function updateInventoryItem(User $actor, User $owner, LedgerInventoryItem $item, array $data): LedgerInventoryItem
    {
        $this->ensureOwnedRecord($owner, $item);

        return DB::transaction(function () use ($actor, $owner, $item, $data) {
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $sourceType = $data['source_type'];
            $referenceId = $data['uex_reference_id'] ?? null;

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

            $item->forceFill([
                'wipe_cycle_id' => $wipeCycle->id,
                'source_type' => $sourceType,
                'uex_reference_type' => $data['uex_reference_type'] ?? $sourceType,
                'uex_reference_id' => $referenceId,
                'custom_name' => $data['custom_name'] ?? null,
                'category' => $data['category'] ?? null,
                'quantity' => $data['quantity'] ?? 1,
                'unit_label' => $data['unit_label'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'terminal_uex_id' => $data['terminal_uex_id'] ?? null,
                'assigned_ship_asset_id' => $this->resolveShipAssetId($owner, $data['assigned_ship_asset_id'] ?? null),
                'purchase_price' => $data['purchase_price'] ?? null,
                'estimated_value' => $data['estimated_value'] ?? null,
                'currency' => $data['currency'] ?? 'aUEC',
                'status' => $data['status'] ?? 'owned',
                'acquired_at' => $data['acquired_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->logActivity($actor, $owner, $wipeCycle, 'inventory.updated', $item, [
                'source_type' => $item->source_type,
                'quantity' => $item->quantity,
            ]);

            return $item->fresh(['assignedShipAsset:id,custom_name,serial_or_label,vehicle_uex_id']);
        });
    }

    public function deleteInventoryItem(User $actor, User $owner, LedgerInventoryItem $item): void
    {
        $this->ensureOwnedRecord($owner, $item);

        $this->logActivity($actor, $owner, $item->wipeCycle, 'inventory.deleted', $item, [
            'source_type' => $item->source_type,
            'quantity' => $item->quantity,
        ]);

        $item->delete();
    }

    public function updateShipAsset(User $actor, User $owner, LedgerShipAsset $asset, array $data): LedgerShipAsset
    {
        $this->ensureOwnedRecord($owner, $asset);

        return DB::transaction(function () use ($actor, $owner, $asset, $data) {
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);

            $asset->forceFill([
                'wipe_cycle_id' => $wipeCycle->id,
                'vehicle_uex_id' => $data['vehicle_uex_id'] ?? null,
                'custom_name' => $data['custom_name'] ?? null,
                'serial_or_label' => $data['serial_or_label'] ?? null,
                'purchase_price' => $data['purchase_price'] ?? null,
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

    public function deleteShipAsset(User $actor, User $owner, LedgerShipAsset $asset): void
    {
        $this->ensureOwnedRecord($owner, $asset);

        $this->logActivity($actor, $owner, $asset->wipeCycle, 'ship_asset.deleted', $asset, [
            'vehicle_uex_id' => $asset->vehicle_uex_id,
            'status' => $asset->status,
        ]);

        $asset->delete();
    }

    public function createSquadronTransaction(User $actor, Squadron $squadron, array $data): LedgerTransaction
    {
        return DB::transaction(function () use ($actor, $squadron, $data) {
            $account = $this->resolveSquadronAccount($squadron, $actor, $data['ledger_account_id'] ?? null);
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $amount = round((float) $data['amount'], 2);

            if ($data['type'] === 'adjustment') {
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

            if ($data['type'] !== 'adjustment' && $amount < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Only adjustments may use negative values.',
                ]);
            }

            $transaction = LedgerTransaction::query()->create([
                'user_id' => $actor->id,
                'squadron_id' => $squadron->id,
                'is_org_owned' => false,
                'ledger_account_id' => $account->id,
                'wipe_cycle_id' => $wipeCycle->id,
                'type' => $data['type'],
                'amount' => $amount,
                'currency' => $data['currency'] ?? $account->currency ?? 'aUEC',
                'source_type' => $data['source_type'] ?? null,
                'description' => $data['description'],
                'transaction_date' => $data['transaction_date'] ?? now(),
                'related_ship_asset_id' => $this->resolveSquadronShipAssetId($squadron, $data['related_ship_asset_id'] ?? null),
                'related_operation_id' => $data['related_operation_id'] ?? null,
                'related_uex_type' => $data['related_uex_type'] ?? null,
                'related_uex_id' => $data['related_uex_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logActivity($actor, $actor, $wipeCycle, 'transaction.created', $transaction, [
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'description' => $transaction->description,
            ], $squadron);

            return $transaction;
        });
    }

    public function updateSquadronTransaction(User $actor, Squadron $squadron, LedgerTransaction $transaction, array $data): LedgerTransaction
    {
        $this->ensureSquadronRecord($squadron, $transaction);
        $this->ensureTransactionIsManuallyEditable($transaction);

        return DB::transaction(function () use ($actor, $squadron, $transaction, $data) {
            $account = $this->resolveSquadronAccount($squadron, $actor, $data['ledger_account_id'] ?? null);
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $amount = round((float) $data['amount'], 2);

            if ($data['type'] === 'adjustment') {
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

            if ($data['type'] !== 'adjustment' && $amount < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Only adjustments may use negative values.',
                ]);
            }

            $transaction->forceFill([
                'ledger_account_id' => $account->id,
                'wipe_cycle_id' => $wipeCycle->id,
                'type' => $data['type'],
                'amount' => $amount,
                'currency' => $data['currency'] ?? $account->currency ?? 'aUEC',
                'source_type' => $data['source_type'] ?? null,
                'description' => $data['description'],
                'transaction_date' => $data['transaction_date'] ?? now(),
                'related_ship_asset_id' => $this->resolveSquadronShipAssetId($squadron, $data['related_ship_asset_id'] ?? null),
                'related_operation_id' => $data['related_operation_id'] ?? null,
                'related_uex_type' => $data['related_uex_type'] ?? null,
                'related_uex_id' => $data['related_uex_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->logActivity($actor, $actor, $wipeCycle, 'transaction.updated', $transaction, [
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'description' => $transaction->description,
            ], $squadron);

            return $transaction->fresh(['account:id,name', 'shipAsset:id,custom_name,serial_or_label,vehicle_uex_id']);
        });
    }

    public function deleteSquadronTransaction(User $actor, Squadron $squadron, LedgerTransaction $transaction): void
    {
        $this->ensureSquadronRecord($squadron, $transaction);
        $this->ensureTransactionIsManuallyEditable($transaction);

        $this->logActivity($actor, $actor, $transaction->wipeCycle, 'transaction.deleted', $transaction, [
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'description' => $transaction->description,
        ], $squadron);

        $transaction->delete();
    }

    public function createSquadronTrade(User $actor, Squadron $squadron, array $data): LedgerTrade
    {
        return DB::transaction(function () use ($actor, $squadron, $data) {
            $account = $this->resolveSquadronAccount($squadron, $actor, $data['ledger_account_id'] ?? null);
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $quantity = round((float) $data['quantity'], 4);
            $buyPrice = round((float) $data['buy_price_per_unit'], 2);
            $sellPrice = round((float) $data['sell_price_per_unit'], 2);

            if ($quantity <= 0 || $buyPrice < 0 || $sellPrice < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Trade quantities and prices must be valid positive values.',
                ]);
            }

            $totalCost = round($quantity * $buyPrice, 2);
            $totalRevenue = round($quantity * $sellPrice, 2);
            $profit = round($totalRevenue - $totalCost, 2);
            $profitPerUnit = $quantity > 0 ? round($profit / $quantity, 2) : 0.0;

            $trade = LedgerTrade::query()->create([
                'user_id' => $actor->id,
                'squadron_id' => $squadron->id,
                'is_org_owned' => false,
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
                'ship_asset_id' => $this->resolveSquadronShipAssetId($squadron, $data['ship_asset_id'] ?? null),
                'cargo_capacity_used' => $data['cargo_capacity_used'] ?? null,
                'trade_date' => $data['trade_date'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logActivity($actor, $actor, $wipeCycle, 'trade.created', $trade, [
                'quantity' => $trade->quantity,
                'profit' => $trade->profit,
            ], $squadron);

            return $trade;
        });
    }

    public function updateSquadronTrade(User $actor, Squadron $squadron, LedgerTrade $trade, array $data): LedgerTrade
    {
        $this->ensureSquadronRecord($squadron, $trade);

        return DB::transaction(function () use ($actor, $squadron, $trade, $data) {
            $account = $this->resolveSquadronAccount($squadron, $actor, $data['ledger_account_id'] ?? null);
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $quantity = round((float) $data['quantity'], 4);
            $buyPrice = round((float) $data['buy_price_per_unit'], 2);
            $sellPrice = round((float) $data['sell_price_per_unit'], 2);

            if ($quantity <= 0 || $buyPrice < 0 || $sellPrice < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Trade quantities and prices must be valid positive values.',
                ]);
            }

            $totalCost = round($quantity * $buyPrice, 2);
            $totalRevenue = round($quantity * $sellPrice, 2);
            $profit = round($totalRevenue - $totalCost, 2);
            $profitPerUnit = $quantity > 0 ? round($profit / $quantity, 2) : 0.0;

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
                'ship_asset_id' => $this->resolveSquadronShipAssetId($squadron, $data['ship_asset_id'] ?? null),
                'cargo_capacity_used' => $data['cargo_capacity_used'] ?? null,
                'trade_date' => $data['trade_date'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->logActivity($actor, $actor, $wipeCycle, 'trade.updated', $trade, [
                'quantity' => $trade->quantity,
                'profit' => $trade->profit,
            ], $squadron);

            return $trade->fresh(['account:id,name', 'shipAsset:id,custom_name,serial_or_label,vehicle_uex_id']);
        });
    }

    public function deleteSquadronTrade(User $actor, Squadron $squadron, LedgerTrade $trade): void
    {
        $this->ensureSquadronRecord($squadron, $trade);

        $this->logActivity($actor, $actor, $trade->wipeCycle, 'trade.deleted', $trade, [
            'quantity' => $trade->quantity,
            'profit' => $trade->profit,
        ], $squadron);

        $trade->delete();
    }

    public function createSquadronInventoryItem(User $actor, Squadron $squadron, array $data): LedgerInventoryItem
    {
        return DB::transaction(function () use ($actor, $squadron, $data) {
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $sourceType = $data['source_type'];
            $referenceId = $data['uex_reference_id'] ?? null;

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

            $item = LedgerInventoryItem::query()->create([
                'user_id' => $actor->id,
                'squadron_id' => $squadron->id,
                'is_org_owned' => false,
                'wipe_cycle_id' => $wipeCycle->id,
                'source_type' => $sourceType,
                'uex_reference_type' => $data['uex_reference_type'] ?? ($sourceType === 'custom' ? null : $sourceType),
                'uex_reference_id' => $referenceId,
                'custom_name' => $data['custom_name'] ?? null,
                'category' => $data['category'] ?? null,
                'quantity' => $data['quantity'],
                'unit_label' => $data['unit_label'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'terminal_uex_id' => $data['terminal_uex_id'] ?? null,
                'assigned_ship_asset_id' => $this->resolveSquadronShipAssetId($squadron, $data['assigned_ship_asset_id'] ?? null),
                'purchase_price' => $data['purchase_price'] ?? null,
                'estimated_value' => $data['estimated_value'] ?? null,
                'currency' => $data['currency'] ?? 'aUEC',
                'status' => $data['status'] ?? 'owned',
                'acquired_at' => $data['acquired_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logActivity($actor, $actor, $wipeCycle, 'inventory.created', $item, [
                'source_type' => $item->source_type,
                'quantity' => $item->quantity,
            ], $squadron);

            return $item;
        });
    }

    public function updateSquadronInventoryItem(User $actor, Squadron $squadron, LedgerInventoryItem $item, array $data): LedgerInventoryItem
    {
        $this->ensureSquadronRecord($squadron, $item);

        return DB::transaction(function () use ($actor, $squadron, $item, $data) {
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $sourceType = $data['source_type'];
            $referenceId = $data['uex_reference_id'] ?? null;

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

            $item->forceFill([
                'wipe_cycle_id' => $wipeCycle->id,
                'source_type' => $sourceType,
                'uex_reference_type' => $data['uex_reference_type'] ?? ($sourceType === 'custom' ? null : $sourceType),
                'uex_reference_id' => $referenceId,
                'custom_name' => $data['custom_name'] ?? null,
                'category' => $data['category'] ?? null,
                'quantity' => $data['quantity'],
                'unit_label' => $data['unit_label'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'terminal_uex_id' => $data['terminal_uex_id'] ?? null,
                'assigned_ship_asset_id' => $this->resolveSquadronShipAssetId($squadron, $data['assigned_ship_asset_id'] ?? null),
                'purchase_price' => $data['purchase_price'] ?? null,
                'estimated_value' => $data['estimated_value'] ?? null,
                'currency' => $data['currency'] ?? 'aUEC',
                'status' => $data['status'] ?? 'owned',
                'acquired_at' => $data['acquired_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->logActivity($actor, $actor, $wipeCycle, 'inventory.updated', $item, [
                'source_type' => $item->source_type,
                'quantity' => $item->quantity,
            ], $squadron);

            return $item->fresh(['assignedShipAsset:id,custom_name,serial_or_label,vehicle_uex_id']);
        });
    }

    public function deleteSquadronInventoryItem(User $actor, Squadron $squadron, LedgerInventoryItem $item): void
    {
        $this->ensureSquadronRecord($squadron, $item);

        $this->logActivity($actor, $actor, $item->wipeCycle, 'inventory.deleted', $item, [
            'source_type' => $item->source_type,
            'quantity' => $item->quantity,
        ], $squadron);

        $item->delete();
    }

    public function createSquadronShipAsset(User $actor, Squadron $squadron, array $data): LedgerShipAsset
    {
        return DB::transaction(function () use ($actor, $squadron, $data) {
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);

            $asset = LedgerShipAsset::query()->create([
                'user_id' => $actor->id,
                'squadron_id' => $squadron->id,
                'is_org_owned' => false,
                'wipe_cycle_id' => $wipeCycle->id,
                'vehicle_uex_id' => $data['vehicle_uex_id'] ?? null,
                'custom_name' => $data['custom_name'] ?? null,
                'serial_or_label' => $data['serial_or_label'] ?? null,
                'purchase_price' => $data['purchase_price'] ?? null,
                'currency' => $data['currency'] ?? 'aUEC',
                'acquisition_source' => $data['acquisition_source'] ?? null,
                'current_location' => $data['current_location'] ?? null,
                'status' => $data['status'] ?? 'owned',
                'acquired_at' => $data['acquired_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logActivity($actor, $actor, $wipeCycle, 'ship_asset.created', $asset, [
                'vehicle_uex_id' => $asset->vehicle_uex_id,
                'status' => $asset->status,
            ], $squadron);

            return $asset;
        });
    }

    public function updateSquadronShipAsset(User $actor, Squadron $squadron, LedgerShipAsset $asset, array $data): LedgerShipAsset
    {
        $this->ensureSquadronRecord($squadron, $asset);

        return DB::transaction(function () use ($actor, $squadron, $asset, $data) {
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);

            $asset->forceFill([
                'wipe_cycle_id' => $wipeCycle->id,
                'vehicle_uex_id' => $data['vehicle_uex_id'] ?? null,
                'custom_name' => $data['custom_name'] ?? null,
                'serial_or_label' => $data['serial_or_label'] ?? null,
                'purchase_price' => $data['purchase_price'] ?? null,
                'currency' => $data['currency'] ?? 'aUEC',
                'acquisition_source' => $data['acquisition_source'] ?? null,
                'current_location' => $data['current_location'] ?? null,
                'status' => $data['status'] ?? 'owned',
                'acquired_at' => $data['acquired_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->logActivity($actor, $actor, $wipeCycle, 'ship_asset.updated', $asset, [
                'vehicle_uex_id' => $asset->vehicle_uex_id,
                'status' => $asset->status,
            ], $squadron);

            return $asset->fresh();
        });
    }

    public function deleteSquadronShipAsset(User $actor, Squadron $squadron, LedgerShipAsset $asset): void
    {
        $this->ensureSquadronRecord($squadron, $asset);

        $this->logActivity($actor, $actor, $asset->wipeCycle, 'ship_asset.deleted', $asset, [
            'vehicle_uex_id' => $asset->vehicle_uex_id,
            'status' => $asset->status,
        ], $squadron);

        $asset->delete();
    }

    public function createOrgTransaction(User $actor, array $data): LedgerTransaction
    {
        return DB::transaction(function () use ($actor, $data) {
            $account = $this->resolveOrgAccount($actor, $data['ledger_account_id'] ?? null);
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $amount = round((float) $data['amount'], 2);

            if ($data['type'] === 'adjustment') {
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

            if ($data['type'] !== 'adjustment' && $amount < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Only adjustments may use negative values.',
                ]);
            }

            $transaction = LedgerTransaction::query()->create([
                'user_id' => $actor->id,
                'squadron_id' => null,
                'is_org_owned' => true,
                'ledger_account_id' => $account->id,
                'wipe_cycle_id' => $wipeCycle->id,
                'type' => $data['type'],
                'amount' => $amount,
                'currency' => $data['currency'] ?? $account->currency ?? 'aUEC',
                'source_type' => $data['source_type'] ?? null,
                'description' => $data['description'],
                'transaction_date' => $data['transaction_date'] ?? now(),
                'related_ship_asset_id' => $this->resolveOrgShipAssetId($data['related_ship_asset_id'] ?? null),
                'related_operation_id' => $data['related_operation_id'] ?? null,
                'related_uex_type' => $data['related_uex_type'] ?? null,
                'related_uex_id' => $data['related_uex_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logActivity($actor, $actor, $wipeCycle, 'transaction.created', $transaction, [
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'description' => $transaction->description,
            ], null, true);

            return $transaction;
        });
    }

    public function updateOrgTransaction(User $actor, LedgerTransaction $transaction, array $data): LedgerTransaction
    {
        $this->ensureOrgRecord($transaction);
        $this->ensureTransactionIsManuallyEditable($transaction);

        return DB::transaction(function () use ($actor, $transaction, $data) {
            $account = $this->resolveOrgAccount($actor, $data['ledger_account_id'] ?? null);
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $amount = round((float) $data['amount'], 2);

            if ($data['type'] === 'adjustment') {
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

            if ($data['type'] !== 'adjustment' && $amount < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Only adjustments may use negative values.',
                ]);
            }

            $transaction->forceFill([
                'ledger_account_id' => $account->id,
                'wipe_cycle_id' => $wipeCycle->id,
                'type' => $data['type'],
                'amount' => $amount,
                'currency' => $data['currency'] ?? $account->currency ?? 'aUEC',
                'source_type' => $data['source_type'] ?? null,
                'description' => $data['description'],
                'transaction_date' => $data['transaction_date'] ?? now(),
                'related_ship_asset_id' => $this->resolveOrgShipAssetId($data['related_ship_asset_id'] ?? null),
                'related_operation_id' => $data['related_operation_id'] ?? null,
                'related_uex_type' => $data['related_uex_type'] ?? null,
                'related_uex_id' => $data['related_uex_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->logActivity($actor, $actor, $wipeCycle, 'transaction.updated', $transaction, [
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'description' => $transaction->description,
            ], null, true);

            return $transaction->fresh(['account:id,name', 'shipAsset:id,custom_name,serial_or_label,vehicle_uex_id']);
        });
    }

    public function deleteOrgTransaction(User $actor, LedgerTransaction $transaction): void
    {
        $this->ensureOrgRecord($transaction);
        $this->ensureTransactionIsManuallyEditable($transaction);

        $this->logActivity($actor, $actor, $transaction->wipeCycle, 'transaction.deleted', $transaction, [
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'description' => $transaction->description,
        ], null, true);

        $transaction->delete();
    }

    public function createOrgTrade(User $actor, array $data): LedgerTrade
    {
        return DB::transaction(function () use ($actor, $data) {
            $account = $this->resolveOrgAccount($actor, $data['ledger_account_id'] ?? null);
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $quantity = round((float) $data['quantity'], 4);
            $buyPrice = round((float) $data['buy_price_per_unit'], 2);
            $sellPrice = round((float) $data['sell_price_per_unit'], 2);

            if ($quantity <= 0 || $buyPrice < 0 || $sellPrice < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Trade quantities and prices must be valid positive values.',
                ]);
            }

            $totalCost = round($quantity * $buyPrice, 2);
            $totalRevenue = round($quantity * $sellPrice, 2);
            $profit = round($totalRevenue - $totalCost, 2);
            $profitPerUnit = $quantity > 0 ? round($profit / $quantity, 2) : 0.0;

            $trade = LedgerTrade::query()->create([
                'user_id' => $actor->id,
                'squadron_id' => null,
                'is_org_owned' => true,
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
                'ship_asset_id' => $this->resolveOrgShipAssetId($data['ship_asset_id'] ?? null),
                'cargo_capacity_used' => $data['cargo_capacity_used'] ?? null,
                'trade_date' => $data['trade_date'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logActivity($actor, $actor, $wipeCycle, 'trade.created', $trade, [
                'quantity' => $trade->quantity,
                'profit' => $trade->profit,
            ], null, true);

            return $trade;
        });
    }

    public function updateOrgTrade(User $actor, LedgerTrade $trade, array $data): LedgerTrade
    {
        $this->ensureOrgRecord($trade);

        return DB::transaction(function () use ($actor, $trade, $data) {
            $account = $this->resolveOrgAccount($actor, $data['ledger_account_id'] ?? null);
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $quantity = round((float) $data['quantity'], 4);
            $buyPrice = round((float) $data['buy_price_per_unit'], 2);
            $sellPrice = round((float) $data['sell_price_per_unit'], 2);

            if ($quantity <= 0 || $buyPrice < 0 || $sellPrice < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Trade quantities and prices must be valid positive values.',
                ]);
            }

            $totalCost = round($quantity * $buyPrice, 2);
            $totalRevenue = round($quantity * $sellPrice, 2);
            $profit = round($totalRevenue - $totalCost, 2);
            $profitPerUnit = $quantity > 0 ? round($profit / $quantity, 2) : 0.0;

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
                'ship_asset_id' => $this->resolveOrgShipAssetId($data['ship_asset_id'] ?? null),
                'cargo_capacity_used' => $data['cargo_capacity_used'] ?? null,
                'trade_date' => $data['trade_date'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->logActivity($actor, $actor, $wipeCycle, 'trade.updated', $trade, [
                'quantity' => $trade->quantity,
                'profit' => $trade->profit,
            ], null, true);

            return $trade->fresh(['account:id,name', 'shipAsset:id,custom_name,serial_or_label,vehicle_uex_id']);
        });
    }

    public function deleteOrgTrade(User $actor, LedgerTrade $trade): void
    {
        $this->ensureOrgRecord($trade);

        $this->logActivity($actor, $actor, $trade->wipeCycle, 'trade.deleted', $trade, [
            'quantity' => $trade->quantity,
            'profit' => $trade->profit,
        ], null, true);

        $trade->delete();
    }

    public function createOrgInventoryItem(User $actor, array $data): LedgerInventoryItem
    {
        return DB::transaction(function () use ($actor, $data) {
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $sourceType = $data['source_type'];
            $referenceId = $data['uex_reference_id'] ?? null;

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

            $item = LedgerInventoryItem::query()->create([
                'user_id' => $actor->id,
                'squadron_id' => null,
                'is_org_owned' => true,
                'wipe_cycle_id' => $wipeCycle->id,
                'source_type' => $sourceType,
                'uex_reference_type' => $data['uex_reference_type'] ?? ($sourceType === 'custom' ? null : $sourceType),
                'uex_reference_id' => $referenceId,
                'custom_name' => $data['custom_name'] ?? null,
                'category' => $data['category'] ?? null,
                'quantity' => $data['quantity'],
                'unit_label' => $data['unit_label'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'terminal_uex_id' => $data['terminal_uex_id'] ?? null,
                'assigned_ship_asset_id' => $this->resolveOrgShipAssetId($data['assigned_ship_asset_id'] ?? null),
                'purchase_price' => $data['purchase_price'] ?? null,
                'estimated_value' => $data['estimated_value'] ?? null,
                'currency' => $data['currency'] ?? 'aUEC',
                'status' => $data['status'] ?? 'owned',
                'acquired_at' => $data['acquired_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logActivity($actor, $actor, $wipeCycle, 'inventory.created', $item, [
                'source_type' => $item->source_type,
                'quantity' => $item->quantity,
            ], null, true);

            return $item;
        });
    }

    public function updateOrgInventoryItem(User $actor, LedgerInventoryItem $item, array $data): LedgerInventoryItem
    {
        $this->ensureOrgRecord($item);

        return DB::transaction(function () use ($actor, $item, $data) {
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $sourceType = $data['source_type'];
            $referenceId = $data['uex_reference_id'] ?? null;

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

            $item->forceFill([
                'wipe_cycle_id' => $wipeCycle->id,
                'source_type' => $sourceType,
                'uex_reference_type' => $data['uex_reference_type'] ?? ($sourceType === 'custom' ? null : $sourceType),
                'uex_reference_id' => $referenceId,
                'custom_name' => $data['custom_name'] ?? null,
                'category' => $data['category'] ?? null,
                'quantity' => $data['quantity'],
                'unit_label' => $data['unit_label'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'terminal_uex_id' => $data['terminal_uex_id'] ?? null,
                'assigned_ship_asset_id' => $this->resolveOrgShipAssetId($data['assigned_ship_asset_id'] ?? null),
                'purchase_price' => $data['purchase_price'] ?? null,
                'estimated_value' => $data['estimated_value'] ?? null,
                'currency' => $data['currency'] ?? 'aUEC',
                'status' => $data['status'] ?? 'owned',
                'acquired_at' => $data['acquired_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->logActivity($actor, $actor, $wipeCycle, 'inventory.updated', $item, [
                'source_type' => $item->source_type,
                'quantity' => $item->quantity,
            ], null, true);

            return $item->fresh(['assignedShipAsset:id,custom_name,serial_or_label,vehicle_uex_id']);
        });
    }

    public function deleteOrgInventoryItem(User $actor, LedgerInventoryItem $item): void
    {
        $this->ensureOrgRecord($item);

        $this->logActivity($actor, $actor, $item->wipeCycle, 'inventory.deleted', $item, [
            'source_type' => $item->source_type,
            'quantity' => $item->quantity,
        ], null, true);

        $item->delete();
    }

    public function createOrgShipAsset(User $actor, array $data): LedgerShipAsset
    {
        return DB::transaction(function () use ($actor, $data) {
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);

            $asset = LedgerShipAsset::query()->create([
                'user_id' => $actor->id,
                'squadron_id' => null,
                'is_org_owned' => true,
                'wipe_cycle_id' => $wipeCycle->id,
                'vehicle_uex_id' => $data['vehicle_uex_id'] ?? null,
                'custom_name' => $data['custom_name'] ?? null,
                'serial_or_label' => $data['serial_or_label'] ?? null,
                'purchase_price' => $data['purchase_price'] ?? null,
                'currency' => $data['currency'] ?? 'aUEC',
                'acquisition_source' => $data['acquisition_source'] ?? null,
                'current_location' => $data['current_location'] ?? null,
                'status' => $data['status'] ?? 'owned',
                'acquired_at' => $data['acquired_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logActivity($actor, $actor, $wipeCycle, 'ship_asset.created', $asset, [
                'vehicle_uex_id' => $asset->vehicle_uex_id,
                'status' => $asset->status,
            ], null, true);

            return $asset;
        });
    }

    public function updateOrgShipAsset(User $actor, LedgerShipAsset $asset, array $data): LedgerShipAsset
    {
        $this->ensureOrgRecord($asset);

        return DB::transaction(function () use ($actor, $asset, $data) {
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);

            $asset->forceFill([
                'wipe_cycle_id' => $wipeCycle->id,
                'vehicle_uex_id' => $data['vehicle_uex_id'] ?? null,
                'custom_name' => $data['custom_name'] ?? null,
                'serial_or_label' => $data['serial_or_label'] ?? null,
                'purchase_price' => $data['purchase_price'] ?? null,
                'currency' => $data['currency'] ?? 'aUEC',
                'acquisition_source' => $data['acquisition_source'] ?? null,
                'current_location' => $data['current_location'] ?? null,
                'status' => $data['status'] ?? 'owned',
                'acquired_at' => $data['acquired_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->logActivity($actor, $actor, $wipeCycle, 'ship_asset.updated', $asset, [
                'vehicle_uex_id' => $asset->vehicle_uex_id,
                'status' => $asset->status,
            ], null, true);

            return $asset->fresh();
        });
    }

    public function deleteOrgShipAsset(User $actor, LedgerShipAsset $asset): void
    {
        $this->ensureOrgRecord($asset);

        $this->logActivity($actor, $actor, $asset->wipeCycle, 'ship_asset.deleted', $asset, [
            'vehicle_uex_id' => $asset->vehicle_uex_id,
            'status' => $asset->status,
        ], null, true);

        $asset->delete();
    }

    public function transferFundsFromPersonal(User $actor, array $data): array
    {
        return $this->performFundTransfer(
            $actor,
            $this->transferContextForPersonal($actor),
            $data
        );
    }

    public function transferFundsFromSquadron(User $actor, Squadron $squadron, array $data): array
    {
        return $this->performFundTransfer(
            $actor,
            $this->transferContextForSquadron($actor, $squadron),
            $data
        );
    }

    public function transferFundsFromOrganization(User $actor, array $data): array
    {
        return $this->performFundTransfer(
            $actor,
            $this->transferContextForOrganization($actor),
            $data
        );
    }

    public function transferInventoryFromPersonal(User $actor, array $data): array
    {
        return $this->performInventoryTransfer(
            $actor,
            $this->transferContextForPersonal($actor),
            $data
        );
    }

    public function transferInventoryFromSquadron(User $actor, Squadron $squadron, array $data): array
    {
        return $this->performInventoryTransfer(
            $actor,
            $this->transferContextForSquadron($actor, $squadron),
            $data
        );
    }

    public function transferInventoryFromOrganization(User $actor, array $data): array
    {
        return $this->performInventoryTransfer(
            $actor,
            $this->transferContextForOrganization($actor),
            $data
        );
    }

    protected function performFundTransfer(User $actor, array $sourceContext, array $data): array
    {
        return DB::transaction(function () use ($actor, $sourceContext, $data) {
            $wipeCycle = $this->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $destinationContext = $this->resolveTransferDestination($actor, $sourceContext, $data);
            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Transfer amounts must be greater than zero.',
                ]);
            }

            $memo = trim((string) ($data['description'] ?? ''));
            $transactionDate = $data['transaction_date'] ?? now();

            $outgoing = LedgerTransaction::query()->create([
                'user_id' => $sourceContext['user_id'],
                'squadron_id' => $sourceContext['squadron_id'],
                'is_org_owned' => $sourceContext['is_org_owned'],
                'ledger_account_id' => $sourceContext['account']->id,
                'wipe_cycle_id' => $wipeCycle->id,
                'type' => 'expense',
                'amount' => $amount,
                'currency' => $sourceContext['account']->currency ?? 'aUEC',
                'source_type' => 'transfer',
                'description' => "Transfer to {$destinationContext['label']}: {$memo}",
                'transaction_date' => $transactionDate,
                'notes' => $data['notes'] ?? null,
            ]);

            $incoming = LedgerTransaction::query()->create([
                'user_id' => $destinationContext['user_id'],
                'squadron_id' => $destinationContext['squadron_id'],
                'is_org_owned' => $destinationContext['is_org_owned'],
                'ledger_account_id' => $destinationContext['account']->id,
                'wipe_cycle_id' => $wipeCycle->id,
                'type' => 'income',
                'amount' => $amount,
                'currency' => $destinationContext['account']->currency ?? 'aUEC',
                'source_type' => 'transfer',
                'description' => "Transfer from {$sourceContext['label']}: {$memo}",
                'transaction_date' => $transactionDate,
                'notes' => $data['notes'] ?? null,
            ]);

            $metadata = [
                'amount' => $amount,
                'currency' => $sourceContext['account']->currency ?? 'aUEC',
                'description' => $memo,
                'from_label' => $sourceContext['label'],
                'to_label' => $destinationContext['label'],
            ];

            $this->logActivity(
                $actor,
                $sourceContext['subject_user'],
                $wipeCycle,
                'transfer.created',
                $outgoing,
                array_merge($metadata, ['direction' => 'outgoing']),
                $sourceContext['squadron'],
                $sourceContext['is_org_owned']
            );

            $this->logActivity(
                $actor,
                $destinationContext['subject_user'],
                $wipeCycle,
                'transfer.created',
                $incoming,
                array_merge($metadata, ['direction' => 'incoming']),
                $destinationContext['squadron'],
                $destinationContext['is_org_owned']
            );

            return [
                'outgoing' => $outgoing,
                'incoming' => $incoming,
            ];
        });
    }

    protected function performInventoryTransfer(User $actor, array $sourceContext, array $data): array
    {
        return DB::transaction(function () use ($actor, $sourceContext, $data) {
            $sourceItem = $this->resolveInventoryTransferItem($sourceContext, (int) $data['inventory_item_id']);
            $destinationContext = $this->resolveTransferDestination($actor, $sourceContext, $data);
            $requestedQuantity = round((float) $data['quantity'], 4);
            $availableQuantity = round((float) $sourceItem->quantity, 4);

            if ($requestedQuantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Transfer quantity must be greater than zero.',
                ]);
            }

            if ($requestedQuantity > $availableQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'You cannot transfer more than the quantity currently tracked in that record.',
                ]);
            }

            $fullTransfer = abs($requestedQuantity - $availableQuantity) < 0.0001;
            $itemLabel = $sourceItem->custom_name
                ?: $this->resolveReferenceLabel($sourceItem->uex_reference_type, $sourceItem->uex_reference_id, $this->referenceMaps())
                ?: 'Inventory item';
            $metadata = [
                'quantity' => $requestedQuantity,
                'item_label' => $itemLabel,
                'from_label' => $sourceContext['label'],
                'to_label' => $destinationContext['label'],
            ];

            if ($fullTransfer) {
                $sourceItem->forceFill([
                    'user_id' => $destinationContext['user_id'],
                    'squadron_id' => $destinationContext['squadron_id'],
                    'is_org_owned' => $destinationContext['is_org_owned'],
                    'assigned_ship_asset_id' => null,
                    'notes' => $this->appendTransferNote($sourceItem->notes, $data['notes'] ?? null),
                ])->save();

                $destinationItem = $sourceItem->fresh();
            } else {
                $sourceQuantity = (float) $sourceItem->quantity;
                $remainingQuantity = round($sourceQuantity - $requestedQuantity, 4);

                [$remainingPurchasePrice, $movedPurchasePrice] = $this->splitInventoryValue($sourceItem->purchase_price, $sourceQuantity, $requestedQuantity);
                [$remainingEstimatedValue, $movedEstimatedValue] = $this->splitInventoryValue($sourceItem->estimated_value, $sourceQuantity, $requestedQuantity);

                $sourceItem->forceFill([
                    'quantity' => $remainingQuantity,
                    'purchase_price' => $remainingPurchasePrice,
                    'estimated_value' => $remainingEstimatedValue,
                ])->save();

                $destinationItem = LedgerInventoryItem::query()->create([
                    'user_id' => $destinationContext['user_id'],
                    'squadron_id' => $destinationContext['squadron_id'],
                    'is_org_owned' => $destinationContext['is_org_owned'],
                    'wipe_cycle_id' => $sourceItem->wipe_cycle_id,
                    'source_type' => $sourceItem->source_type,
                    'uex_reference_type' => $sourceItem->uex_reference_type,
                    'uex_reference_id' => $sourceItem->uex_reference_id,
                    'custom_name' => $sourceItem->custom_name,
                    'category' => $sourceItem->category,
                    'quantity' => $requestedQuantity,
                    'unit_label' => $sourceItem->unit_label,
                    'location_name' => $sourceItem->location_name,
                    'terminal_uex_id' => $sourceItem->terminal_uex_id,
                    'assigned_ship_asset_id' => null,
                    'purchase_price' => $movedPurchasePrice,
                    'estimated_value' => $movedEstimatedValue,
                    'currency' => $sourceItem->currency,
                    'status' => $sourceItem->status,
                    'acquired_at' => $sourceItem->acquired_at,
                    'notes' => $this->appendTransferNote($sourceItem->notes, $data['notes'] ?? null),
                ]);
            }

            $this->logActivity(
                $actor,
                $sourceContext['subject_user'],
                $sourceItem->wipeCycle,
                'inventory.transfer_out',
                $sourceItem,
                $metadata,
                $sourceContext['squadron'],
                $sourceContext['is_org_owned']
            );

            $this->logActivity(
                $actor,
                $destinationContext['subject_user'],
                $destinationItem->wipeCycle,
                'inventory.transfer_in',
                $destinationItem,
                $metadata,
                $destinationContext['squadron'],
                $destinationContext['is_org_owned']
            );

            return [
                'source' => $sourceItem->fresh(),
                'destination' => $destinationItem->fresh(),
            ];
        });
    }

    public function createWipeCycle(User $actor, array $data): WipeCycle
    {
        return DB::transaction(function () use ($actor, $data) {
            WipeCycle::query()
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'ended_at' => now(),
                ]);

            $wipeCycle = WipeCycle::query()->create([
                'name' => $data['name'],
                'star_citizen_version' => $data['star_citizen_version'] ?? null,
                'wipe_type' => $data['wipe_type'] ?? 'unknown',
                'started_at' => $data['started_at'] ?? now(),
                'ended_at' => null,
                'is_current' => true,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logActivity($actor, null, $wipeCycle, 'wipe_cycle.created', $wipeCycle, [
                'name' => $wipeCycle->name,
                'wipe_type' => $wipeCycle->wipe_type,
            ]);

            return $wipeCycle;
        });
    }

    public function setCurrentWipeCycle(User $actor, WipeCycle $wipeCycle): WipeCycle
    {
        return DB::transaction(function () use ($actor, $wipeCycle) {
            WipeCycle::query()->where('is_current', true)->update(['is_current' => false]);
            $wipeCycle->forceFill([
                'is_current' => true,
                'ended_at' => null,
            ])->save();

            $this->logActivity($actor, null, $wipeCycle, 'wipe_cycle.current_set', $wipeCycle, [
                'name' => $wipeCycle->name,
            ]);

            return $wipeCycle->fresh();
        });
    }

    public function closeWipeCycle(User $actor, WipeCycle $wipeCycle): WipeCycle
    {
        $wipeCycle->forceFill([
            'is_current' => false,
            'ended_at' => $wipeCycle->ended_at ?? now(),
        ])->save();

        $this->logActivity($actor, null, $wipeCycle, 'wipe_cycle.closed', $wipeCycle, [
            'name' => $wipeCycle->name,
        ]);

        if (! WipeCycle::query()->where('is_current', true)->exists()) {
            $this->ensureCurrentWipeCycle();
        }

        return $wipeCycle->fresh();
    }

    public function updateCurrentWipeCycle(User $actor, WipeCycle $wipeCycle, array $data): WipeCycle
    {
        if (! $wipeCycle->is_current) {
            throw ValidationException::withMessages([
                'name' => 'Only the current live cycle can be renamed.',
            ]);
        }

        $wipeCycle->forceFill([
            'name' => $data['name'],
            'star_citizen_version' => $data['star_citizen_version'] ?? null,
            'wipe_type' => $data['wipe_type'] ?? $wipeCycle->wipe_type,
            'started_at' => $data['started_at'] ?? $wipeCycle->started_at,
        ])->save();

        $this->logActivity($actor, null, $wipeCycle, 'wipe_cycle.updated', $wipeCycle, [
            'name' => $wipeCycle->name,
            'wipe_type' => $wipeCycle->wipe_type,
        ]);

        return $wipeCycle->fresh();
    }

    protected function ensureCurrentWipeCycle(): WipeCycle
    {
        return $this->cycles->currentWipeCycle();
    }

    protected function resolveWipeFilter(string $wipeFilter, WipeCycle $currentWipe): array
    {
        return $this->cycles->resolveWipeFilter($wipeFilter, $currentWipe);
    }

    protected function applyWipeFilter($query, array $filter)
    {
        return $this->cycles->applyWipeFilter($query, $filter);
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

    protected function summarizeLedgerCollections(
        Collection $transactions,
        Collection $trades,
        Collection $inventoryItems,
        Collection $shipAssets,
        array $inventorySuggestionMaps,
        Collection $shipPricingMap,
    ): array {
        return $this->summaries->summarizeLedgerCollections(
            $transactions,
            $trades,
            $inventoryItems,
            $shipAssets,
            $inventorySuggestionMaps,
            $shipPricingMap
        );
    }

    protected function buildCycleSummaries(
        Collection $transactions,
        Collection $trades,
        Collection $inventoryItems,
        Collection $shipAssets,
        array $inventorySuggestionMaps,
        Collection $shipPricingMap,
    ): Collection {
        return $this->summaries->buildCycleSummaries(
            $transactions,
            $trades,
            $inventoryItems,
            $shipAssets,
            $inventorySuggestionMaps,
            $shipPricingMap
        );
    }

    protected function buildCycleComparison(Collection $cycleSummaries, array $filter, WipeCycle $currentWipe): ?array
    {
        return $this->summaries->buildCycleComparison($cycleSummaries, $filter, $currentWipe);
    }

    protected function buildReports(User $user, array $filter): array
    {
        $transactions = $this->applyWipeFilter(
            LedgerTransaction::query()->where('user_id', $user->id)->whereNull('squadron_id')->where('is_org_owned', false),
            $filter
        )->get();

        $trades = $this->applyWipeFilter(
            LedgerTrade::query()->where('user_id', $user->id)->whereNull('squadron_id')->where('is_org_owned', false),
            $filter
        )->get();

        $inventory = $this->applyWipeFilter(
            LedgerInventoryItem::query()->where('user_id', $user->id)->whereNull('squadron_id')->where('is_org_owned', false),
            $filter
        )->get();

        $ships = $this->applyWipeFilter(
            LedgerShipAsset::query()->where('user_id', $user->id)->whereNull('squadron_id')->where('is_org_owned', false),
            $filter
        )->get();

        return $this->buildReportsForCollections($transactions, $trades, $inventory, $ships, $filter);
    }

    protected function buildReportsForCollections(
        Collection $transactions,
        Collection $trades,
        Collection $inventory,
        Collection $ships,
        array $filter
    ): array {
        return $this->summaries->buildReportsForCollections($transactions, $trades, $inventory, $ships, $filter);
    }

    protected function tradeProfitBreakdown(?Collection $trades, ?Collection $commodityNames = null): Collection
    {
        return $this->summaries->tradeProfitBreakdown($trades, $commodityNames);
    }

    protected function groupSum(Collection $items, string $groupBy, string $valueKey = 'amount'): array
    {
        return $this->summaries->groupSum($items, $groupBy, $valueKey);
    }

    protected function resolveAccount(User $owner, ?int $accountId): LedgerAccount
    {
        if (! $accountId) {
            return $this->defaultAccountFor($owner);
        }

        $account = LedgerAccount::query()
            ->where('user_id', $owner->id)
            ->whereNull('squadron_id')
            ->find($accountId);

        if (! $account) {
            throw ValidationException::withMessages([
                'ledger_account_id' => 'That ledger account is not available for this member.',
            ]);
        }

        return $account;
    }

    protected function resolveSquadronAccount(Squadron $squadron, User $actor, ?int $accountId): LedgerAccount
    {
        if (! $accountId) {
            return $this->defaultSquadronAccountFor($squadron, $actor);
        }

        $account = LedgerAccount::query()
            ->where('squadron_id', $squadron->id)
            ->where('is_org_owned', false)
            ->find($accountId);

        if (! $account) {
            throw ValidationException::withMessages([
                'ledger_account_id' => 'That ledger account is not available for this squadron.',
            ]);
        }

        return $account;
    }

    protected function resolveOrgAccount(User $actor, ?int $accountId): LedgerAccount
    {
        if (! $accountId) {
            return $this->defaultOrgAccountFor($actor);
        }

        $account = LedgerAccount::query()
            ->whereNull('squadron_id')
            ->where('is_org_owned', true)
            ->find($accountId);

        if (! $account) {
            throw ValidationException::withMessages([
                'ledger_account_id' => 'That ledger account is not available for the org ledger.',
            ]);
        }

        return $account;
    }

    protected function resolveWipeCycle(?int $wipeCycleId): WipeCycle
    {
        return $this->cycles->resolveWipeCycle($wipeCycleId);
    }

    protected function resolveWritableWipeCycle(?int $wipeCycleId): WipeCycle
    {
        return $this->cycles->resolveWritableWipeCycle($wipeCycleId);
    }

    protected function resolveShipAssetId(User $owner, ?int $shipAssetId): ?int
    {
        if (! $shipAssetId) {
            return null;
        }

        $asset = LedgerShipAsset::query()
            ->where('user_id', $owner->id)
            ->whereNull('squadron_id')
            ->where('is_org_owned', false)
            ->find($shipAssetId);

        if (! $asset) {
            throw ValidationException::withMessages([
                'ship_asset_id' => 'That ship asset does not belong to this member.',
            ]);
        }

        return $asset->id;
    }

    protected function resolveSquadronShipAssetId(Squadron $squadron, ?int $shipAssetId): ?int
    {
        if (! $shipAssetId) {
            return null;
        }

        $asset = LedgerShipAsset::query()
            ->where('squadron_id', $squadron->id)
            ->where('is_org_owned', false)
            ->find($shipAssetId);

        if (! $asset) {
            throw ValidationException::withMessages([
                'ship_asset_id' => 'That ship asset does not belong to this squadron.',
            ]);
        }

        return $asset->id;
    }

    protected function resolveOrgShipAssetId(?int $shipAssetId): ?int
    {
        if (! $shipAssetId) {
            return null;
        }

        $asset = LedgerShipAsset::query()
            ->whereNull('squadron_id')
            ->where('is_org_owned', true)
            ->find($shipAssetId);

        if (! $asset) {
            throw ValidationException::withMessages([
                'ship_asset_id' => 'That ship asset does not belong to the org ledger.',
            ]);
        }

        return $asset->id;
    }

    protected function referenceOptions(): array
    {
        return $this->references->referenceOptions();
    }

    protected function tradePricingSuggestions(): array
    {
        return $this->references->tradePricingSuggestions();
    }

    protected function inventoryValuationSuggestions(): array
    {
        return $this->references->inventoryValuationSuggestions();
    }

    protected function shipPricingSuggestions(): array
    {
        return $this->references->shipPricingSuggestions();
    }

    protected function bestPriceRows(
        string $table,
        string $groupColumn,
        string $priceColumn,
        string $direction = 'asc'
    ): Collection {
        return $this->references->bestPriceRows($table, $groupColumn, $priceColumn, $direction);
    }

    protected function referenceMaps(): array
    {
        return $this->references->referenceMaps();
    }

    protected function suggestionMap(array $entries): Collection
    {
        return $this->references->suggestionMap($entries);
    }

    protected function inventorySuggestionMaps(array $references): array
    {
        return $this->references->inventorySuggestionMaps($references);
    }

    protected function inventorySuggestionForItem(LedgerInventoryItem $item, array $inventorySuggestionMaps): ?array
    {
        return $this->references->inventorySuggestionForItem($item, $inventorySuggestionMaps);
    }

    protected function inventoryEstimateMatchesSuggestion(LedgerInventoryItem $item, array $inventorySuggestionMaps): bool
    {
        return $this->references->inventoryEstimateMatchesSuggestion($item, $inventorySuggestionMaps);
    }

    protected function inventoryPurchaseMatchesSuggestion(LedgerInventoryItem $item, array $inventorySuggestionMaps): bool
    {
        return $this->references->inventoryPurchaseMatchesSuggestion($item, $inventorySuggestionMaps);
    }

    protected function shipPurchaseMatchesSuggestion(LedgerShipAsset $asset, Collection $shipPricingMap): bool
    {
        return $this->references->shipPurchaseMatchesSuggestion($asset, $shipPricingMap);
    }

    protected function transferTargetsFor(User $actor, string $currentType, ?Squadron $currentSquadron = null): array
    {
        $targets = [];

        if ($currentType !== 'personal') {
            $targets[] = [
                'key' => 'personal',
                'type' => 'personal',
                'label' => 'My Ledger',
                'description' => 'Move funds into your personal book.',
            ];
        }

        foreach ($this->manageableSquadronTransferTargets($actor) as $target) {
            if ($currentType === 'squadron' && $currentSquadron && (int) $target['squadron_id'] === (int) $currentSquadron->id) {
                continue;
            }

            $targets[] = $target;
        }

        if ($currentType !== 'organization' && $this->canManageOrganizationLedger($actor)) {
            $targets[] = [
                'key' => 'organization',
                'type' => 'organization',
                'label' => 'Org Treasury',
                'description' => 'Move funds into Horizon Treasury.',
            ];
        }

        return $targets;
    }

    protected function manageableSquadronTransferTargets(User $actor): array
    {
        $access = app(AccessService::class);

        return Squadron::query()
            ->whereHas('members', function ($query) use ($actor) {
                $query
                    ->where('user_id', $actor->id)
                    ->where('membership_status', 'active');
            })
            ->orderBy('name')
            ->get()
            ->filter(fn (Squadron $squadron) => $access->canManageSquadronLedger($actor, $squadron))
            ->map(fn (Squadron $squadron) => [
                'key' => "squadron:{$squadron->id}",
                'type' => 'squadron',
                'squadron_id' => $squadron->id,
                'label' => "{$squadron->name} Squadron Ledger",
                'description' => 'Move funds into the shared squadron ledger.',
            ])
            ->values()
            ->all();
    }

    protected function transferInventoryOptions(Collection $items, array $referenceMaps): array
    {
        return $this->references->transferInventoryOptions($items, $referenceMaps);
    }

    protected function presentTransaction(LedgerTransaction $transaction, array $referenceMaps): array
    {
        return $this->references->presentTransaction($transaction, $referenceMaps);
    }

    protected function presentTrade(LedgerTrade $trade, array $referenceMaps): array
    {
        return $this->references->presentTrade($trade, $referenceMaps);
    }

    protected function presentInventoryItem(
        LedgerInventoryItem $item,
        array $referenceMaps,
        array $inventorySuggestionMaps
    ): array
    {
        return $this->references->presentInventoryItem($item, $referenceMaps, $inventorySuggestionMaps);
    }

    protected function presentShipAsset(
        LedgerShipAsset $asset,
        array $referenceMaps,
        Collection $shipPricingMap
    ): array
    {
        return $this->references->presentShipAsset($asset, $referenceMaps, $shipPricingMap);
    }

    protected function presentActivityLog(LedgerActivityLog $log): array
    {
        return $this->references->presentActivityLog($log);
    }

    protected function withLedgerOwner(array $payload, int $userId, Collection $memberLabels): array
    {
        $payload['member_name'] = $memberLabels->get($userId, "Member {$userId}");

        return $payload;
    }

    protected function resolveReferenceLabel(?string $type, $id, array $referenceMaps): ?string
    {
        return $this->references->resolveReferenceLabel($type, $id, $referenceMaps);
    }

    protected function resolveTransferDestination(User $actor, array $sourceContext, array $data): array
    {
        $destination = match ($data['destination_type']) {
            'personal' => $this->transferContextForPersonal($actor),
            'squadron' => $this->transferContextForRequestedSquadron($actor, (int) ($data['destination_squadron_id'] ?? 0)),
            'organization' => $this->transferContextForOrganization($actor),
            default => null,
        };

        if (! $destination) {
            throw ValidationException::withMessages([
                'destination_type' => 'Choose a valid transfer destination.',
            ]);
        }

        if ($sourceContext['type'] === $destination['type']) {
            if ($sourceContext['type'] !== 'squadron'
                || (int) ($sourceContext['squadron_id'] ?? 0) === (int) ($destination['squadron_id'] ?? 0)) {
                throw ValidationException::withMessages([
                    'destination_type' => 'Pick a different ledger for this transfer.',
                ]);
            }
        }

        return $destination;
    }

    protected function transferContextForPersonal(User $actor): array
    {
        $account = $this->defaultAccountFor($actor);

        return [
            'type' => 'personal',
            'label' => $account->name ?: 'My Ledger',
            'account' => $account,
            'user_id' => $actor->id,
            'subject_user' => $actor,
            'squadron_id' => null,
            'squadron' => null,
            'is_org_owned' => false,
        ];
    }

    protected function transferContextForSquadron(User $actor, Squadron $squadron): array
    {
        $access = app(AccessService::class);

        if (! $access->canManageSquadronLedger($actor, $squadron)) {
            throw ValidationException::withMessages([
                'destination_type' => 'You do not have permission to move funds through that squadron ledger.',
            ]);
        }

        $account = $this->resolveSquadronAccount($squadron, $actor, null);

        return [
            'type' => 'squadron',
            'label' => "{$squadron->name} Squadron Ledger",
            'account' => $account,
            'user_id' => $actor->id,
            'subject_user' => $actor,
            'squadron_id' => $squadron->id,
            'squadron' => $squadron,
            'is_org_owned' => false,
        ];
    }

    protected function transferContextForRequestedSquadron(User $actor, int $squadronId): array
    {
        if ($squadronId <= 0) {
            throw ValidationException::withMessages([
                'destination_squadron_id' => 'Choose which squadron ledger should receive the funds.',
            ]);
        }

        $squadron = Squadron::query()->find($squadronId);

        if (! $squadron) {
            throw ValidationException::withMessages([
                'destination_squadron_id' => 'Choose a valid squadron ledger destination.',
            ]);
        }

        return $this->transferContextForSquadron($actor, $squadron);
    }

    protected function transferContextForOrganization(User $actor): array
    {
        if (! $this->canManageOrganizationLedger($actor)) {
            throw ValidationException::withMessages([
                'destination_type' => 'You do not have permission to move funds through Horizon Treasury.',
            ]);
        }

        $account = $this->resolveOrgAccount($actor, null);

        return [
            'type' => 'organization',
            'label' => 'Org Treasury',
            'account' => $account,
            'user_id' => $actor->id,
            'subject_user' => $actor,
            'squadron_id' => null,
            'squadron' => null,
            'is_org_owned' => true,
        ];
    }

    protected function resolveInventoryTransferItem(array $sourceContext, int $inventoryItemId): LedgerInventoryItem
    {
        $item = LedgerInventoryItem::query()->findOrFail($inventoryItemId);

        return match ($sourceContext['type']) {
            'personal' => tap($item, fn (LedgerInventoryItem $inventoryItem) => $this->ensureOwnedRecord($sourceContext['subject_user'], $inventoryItem)),
            'squadron' => tap($item, fn (LedgerInventoryItem $inventoryItem) => $this->ensureSquadronRecord($sourceContext['squadron'], $inventoryItem)),
            'organization' => tap($item, fn (LedgerInventoryItem $inventoryItem) => $this->ensureOrgRecord($inventoryItem)),
        };
    }

    protected function splitInventoryValue($value, float $sourceQuantity, float $movedQuantity): array
    {
        if ($value === null) {
            return [null, null];
        }

        if ($sourceQuantity <= 0) {
            return [$value, null];
        }

        $movedPortion = round(((float) $value) * ($movedQuantity / $sourceQuantity), 2);
        $remainingPortion = round((float) $value - $movedPortion, 2);

        return [$remainingPortion, $movedPortion];
    }

    protected function appendTransferNote(?string $existingNotes, ?string $transferNotes): ?string
    {
        $existing = trim((string) ($existingNotes ?? ''));
        $extra = trim((string) ($transferNotes ?? ''));

        if ($existing === '') {
            return $extra !== '' ? $extra : null;
        }

        if ($extra === '') {
            return $existing;
        }

        return "{$existing}\n\nTransfer note: {$extra}";
    }

    protected function canManageOrganizationLedger(User $actor): bool
    {
        return $actor->can('manage-org-ledger');
    }

    protected function ensureOwnedRecord(User $owner, Model $record): void
    {
        if ((int) $record->getAttribute('user_id') === (int) $owner->id
            && blank($record->getAttribute('squadron_id'))
            && ! (bool) $record->getAttribute('is_org_owned')) {
            $this->ensureRecordIsMutable($record);
            return;
        }

        $exception = new ModelNotFoundException;
        $exception->setModel($record::class, [$record->getKey()]);

        throw $exception;
    }

    protected function ensureSquadronRecord(Squadron $squadron, Model $record): void
    {
        if ((int) $record->getAttribute('squadron_id') === (int) $squadron->id
            && ! (bool) $record->getAttribute('is_org_owned')) {
            $this->ensureRecordIsMutable($record);
            return;
        }

        $exception = new ModelNotFoundException;
        $exception->setModel($record::class, [$record->getKey()]);

        throw $exception;
    }

    protected function ensureOrgRecord(Model $record): void
    {
        if ((bool) $record->getAttribute('is_org_owned') && blank($record->getAttribute('squadron_id'))) {
            $this->ensureRecordIsMutable($record);
            return;
        }

        $exception = new ModelNotFoundException;
        $exception->setModel($record::class, [$record->getKey()]);

        throw $exception;
    }

    protected function ensureRecordIsMutable(Model $record): void
    {
        $wipeCycle = $record->relationLoaded('wipeCycle')
            ? $record->getRelation('wipeCycle')
            : $record->wipeCycle;

        if ($wipeCycle && ! $wipeCycle->is_current) {
            throw ValidationException::withMessages([
                'wipe_cycle_id' => 'Archived cycles are read only. Switch back to the current cycle to make changes.',
            ]);
        }
    }

    protected function ensureTransactionIsManuallyEditable(LedgerTransaction $transaction): void
    {
        if ($transaction->source_type !== 'transfer') {
            return;
        }

        throw ValidationException::withMessages([
            'transaction' => 'Transfer records stay locked so the paired ledgers stay in sync. Create a new transfer or reversal instead.',
        ]);
    }

    protected function logActivity(
        User $actor,
        ?User $subjectUser,
        ?WipeCycle $wipeCycle,
        string $action,
        object $target,
        array $metadata = [],
        ?Squadron $squadron = null,
        bool $isOrgOwned = false
    ): void {
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
}
