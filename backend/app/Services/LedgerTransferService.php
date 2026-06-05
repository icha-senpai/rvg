<?php

namespace App\Services;

use App\Domain\AccessControl\AccessService;
use App\Models\LedgerActivityLog;
use App\Models\LedgerInventoryItem;
use App\Models\LedgerTransferRequest;
use App\Models\LedgerTransaction;
use App\Models\Squadron;
use App\Models\User;
use App\Models\WipeCycle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LedgerTransferService
{
    public function __construct(
        protected LedgerCycleService $cycles,
        protected LedgerReferenceService $references,
        protected LedgerTransferContextService $contexts,
        protected LedgerTransferRequestService $requests,
    ) {}

    public function transferFundsFromPersonal(User $actor, array $data): array
    {
        return $this->performFundTransfer(
            $actor,
            $this->contexts->transferContextForPersonal($actor),
            $data
        );
    }

    public function transferFundsFromSquadron(User $actor, Squadron $squadron, array $data): array
    {
        return $this->performFundTransfer(
            $actor,
            $this->contexts->transferContextForSquadron($actor, $squadron),
            $data
        );
    }

    public function transferFundsFromOrganization(User $actor, array $data): array
    {
        return $this->performFundTransfer(
            $actor,
            $this->contexts->transferContextForOrganization($actor),
            $data
        );
    }

    public function transferInventoryFromPersonal(User $actor, array $data): array
    {
        return $this->performInventoryTransfer(
            $actor,
            $this->contexts->transferContextForPersonal($actor),
            $data
        );
    }

    public function transferInventoryFromSquadron(User $actor, Squadron $squadron, array $data): array
    {
        return $this->performInventoryTransfer(
            $actor,
            $this->contexts->transferContextForSquadron($actor, $squadron),
            $data
        );
    }

    public function transferInventoryFromOrganization(User $actor, array $data): array
    {
        return $this->performInventoryTransfer(
            $actor,
            $this->contexts->transferContextForOrganization($actor),
            $data
        );
    }

    public function approvePendingFundTransferForPersonal(User $actor, LedgerTransferRequest $transferRequest): array
    {
        return $this->requests->approveTransferRequestForContext(
            $actor,
            $transferRequest,
            $this->personalInboxContext($actor),
            'funds',
            fn (User $approver, LedgerTransferRequest $request) => $this->completeFundTransferRequest($approver, $request),
            fn (User $approver, LedgerTransferRequest $request) => $this->completeInventoryTransferRequest($approver, $request)
        );
    }

    public function rejectPendingFundTransferForPersonal(User $actor, LedgerTransferRequest $transferRequest, array $data = []): LedgerTransferRequest
    {
        return $this->requests->rejectTransferRequestForContext($actor, $transferRequest, $this->personalInboxContext($actor), 'funds', $data);
    }

    public function approvePendingInventoryTransferForPersonal(User $actor, LedgerTransferRequest $transferRequest): array
    {
        return $this->requests->approveTransferRequestForContext(
            $actor,
            $transferRequest,
            $this->personalInboxContext($actor),
            'inventory',
            fn (User $approver, LedgerTransferRequest $request) => $this->completeFundTransferRequest($approver, $request),
            fn (User $approver, LedgerTransferRequest $request) => $this->completeInventoryTransferRequest($approver, $request)
        );
    }

    public function rejectPendingInventoryTransferForPersonal(User $actor, LedgerTransferRequest $transferRequest, array $data = []): LedgerTransferRequest
    {
        return $this->requests->rejectTransferRequestForContext($actor, $transferRequest, $this->personalInboxContext($actor), 'inventory', $data);
    }

    public function approvePendingFundTransferForSquadron(User $actor, Squadron $squadron, LedgerTransferRequest $transferRequest): array
    {
        return $this->requests->approveTransferRequestForContext(
            $actor,
            $transferRequest,
            $this->squadronInboxContext($actor, $squadron),
            'funds',
            fn (User $approver, LedgerTransferRequest $request) => $this->completeFundTransferRequest($approver, $request),
            fn (User $approver, LedgerTransferRequest $request) => $this->completeInventoryTransferRequest($approver, $request)
        );
    }

    public function rejectPendingFundTransferForSquadron(User $actor, Squadron $squadron, LedgerTransferRequest $transferRequest, array $data = []): LedgerTransferRequest
    {
        return $this->requests->rejectTransferRequestForContext($actor, $transferRequest, $this->squadronInboxContext($actor, $squadron), 'funds', $data);
    }

    public function approvePendingInventoryTransferForSquadron(User $actor, Squadron $squadron, LedgerTransferRequest $transferRequest): array
    {
        return $this->requests->approveTransferRequestForContext(
            $actor,
            $transferRequest,
            $this->squadronInboxContext($actor, $squadron),
            'inventory',
            fn (User $approver, LedgerTransferRequest $request) => $this->completeFundTransferRequest($approver, $request),
            fn (User $approver, LedgerTransferRequest $request) => $this->completeInventoryTransferRequest($approver, $request)
        );
    }

    public function rejectPendingInventoryTransferForSquadron(User $actor, Squadron $squadron, LedgerTransferRequest $transferRequest, array $data = []): LedgerTransferRequest
    {
        return $this->requests->rejectTransferRequestForContext($actor, $transferRequest, $this->squadronInboxContext($actor, $squadron), 'inventory', $data);
    }

    public function reverseFundTransferForPersonal(User $actor, LedgerTransferRequest $transferRequest, array $data = []): array
    {
        return $this->requests->reverseCompletedFundTransferForContext($actor, $transferRequest, $this->personalInboxContext($actor), $data);
    }

    public function reverseFundTransferForSquadron(User $actor, Squadron $squadron, LedgerTransferRequest $transferRequest, array $data = []): array
    {
        return $this->requests->reverseCompletedFundTransferForContext($actor, $transferRequest, $this->squadronInboxContext($actor, $squadron), $data);
    }

    public function reverseFundTransferForOrganization(User $actor, LedgerTransferRequest $transferRequest, array $data = []): array
    {
        return $this->requests->reverseCompletedFundTransferForContext($actor, $transferRequest, $this->organizationInboxContext($actor), $data);
    }

    public function transferTargetsFor(User $actor, string $currentType, ?Squadron $currentSquadron = null): array
    {
        $targets = [];
        $memberTargets = $this->verifiedMemberTransferTargets($actor, $currentType !== 'personal');

        if ($currentType !== 'personal') {
            $targets[] = [
                'key' => 'personal',
                'type' => 'personal',
                'user_id' => $actor->id,
                'label' => 'My Assets & Funds',
                'description' => 'Move funds or tracked assets into your personal records.',
            ];
        }

        foreach ($this->availableSquadronTransferTargets($actor, $currentSquadron) as $target) {
            $targets[] = $target;
        }

        if (
            $currentType !== 'organization'
            && ($currentType === 'personal' || $this->contexts->canManageOrganizationLedger($actor))
        ) {
            $targets[] = [
                'key' => 'organization',
                'type' => 'organization',
                'label' => 'Horizon Treasury',
                'description' => 'Move funds or tracked assets into Horizon Treasury.',
            ];
        }

        foreach ($memberTargets as $target) {
            $targets[] = $target;
        }

        return $targets;
    }

    public function pendingTransferRequestsForContext(User $viewer, array $context, string $transferKind, array $referenceMaps): array
    {
        return $this->requests->pendingTransferRequestsForContext($viewer, $context, $transferKind, $referenceMaps);
    }

    public function recentFundTransfersForContext(User $viewer, array $context): array
    {
        return $this->requests->recentFundTransfersForContext($viewer, $context);
    }

    public function personalInboxContext(User $actor): array
    {
        return $this->contexts->personalInboxContext($actor);
    }

    public function squadronInboxContext(User $actor, Squadron $squadron): array
    {
        return $this->contexts->squadronInboxContext($actor, $squadron);
    }

    public function organizationInboxContext(User $actor): array
    {
        return $this->contexts->organizationInboxContext($actor);
    }

    protected function performFundTransfer(User $actor, array $sourceContext, array $data): array
    {
        return DB::transaction(function () use ($actor, $sourceContext, $data) {
            $wipeCycle = $this->cycles->resolveWritableWipeCycle($data['wipe_cycle_id'] ?? null);
            $destinationContext = $this->contexts->resolveTransferDestination($actor, $sourceContext, $data);
            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Transfer amounts must be greater than zero.',
                ]);
            }

            $memo = trim((string) ($data['description'] ?? ''));
            $transactionDate = $data['transaction_date'] ?? now();
            $transferRequest = $this->createTransferRequestRecord('funds', $actor, $wipeCycle, $sourceContext, $destinationContext, [
                'amount' => $amount,
                'currency' => $sourceContext['account']->currency ?? 'aUEC',
                'description' => $memo,
                'transaction_date' => $transactionDate,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($this->transferRequiresApproval($actor, $sourceContext, $destinationContext)) {
                $this->logPendingTransferRequest($actor, $transferRequest, $sourceContext, $destinationContext);

                return [
                    'status' => 'pending',
                    'request' => $transferRequest->fresh(),
                ];
            }

            return [
                'status' => 'completed',
                ...$this->completeFundTransferRequest($actor, $transferRequest, $sourceContext, $destinationContext),
            ];
        });
    }

    protected function performInventoryTransfer(User $actor, array $sourceContext, array $data): array
    {
        return DB::transaction(function () use ($actor, $sourceContext, $data) {
            $sourceItem = $this->resolveInventoryTransferItem($sourceContext, (int) $data['inventory_item_id']);
            $destinationContext = $this->contexts->resolveTransferDestination($actor, $sourceContext, $data);
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

            $itemLabel = $sourceItem->custom_name
                ?: $this->resolveReferenceLabel($sourceItem->uex_reference_type, $sourceItem->uex_reference_id, $this->references->referenceMaps())
                ?: 'Inventory item';
            $transferRequest = $this->createTransferRequestRecord('inventory', $actor, $sourceItem->wipeCycle, $sourceContext, $destinationContext, [
                'quantity' => $requestedQuantity,
                'currency' => $sourceItem->currency ?? 'aUEC',
                'description' => $itemLabel,
                'notes' => $data['notes'] ?? null,
                'source_inventory_item_id' => $sourceItem->id,
            ]);

            if ($this->transferRequiresApproval($actor, $sourceContext, $destinationContext)) {
                $this->logPendingInventoryTransferRequest($actor, $transferRequest, $sourceContext, $destinationContext, $itemLabel, $requestedQuantity);

                return [
                    'status' => 'pending',
                    'request' => $transferRequest->fresh(),
                ];
            }

            return [
                'status' => 'completed',
                ...$this->completeInventoryTransferRequest($actor, $transferRequest, $sourceContext, $destinationContext, $sourceItem, $requestedQuantity),
            ];
        });
    }

    protected function createTransferRequestRecord(
        string $transferKind,
        User $actor,
        ?WipeCycle $wipeCycle,
        array $sourceContext,
        array $destinationContext,
        array $attributes
    ): LedgerTransferRequest {
        return LedgerTransferRequest::query()->create([
            'transfer_kind' => $transferKind,
            'status' => 'pending',
            'requested_by_user_id' => $actor->id,
            'wipe_cycle_id' => $wipeCycle?->id,
            'source_user_id' => $sourceContext['user_id'] ?? $actor->id,
            'source_squadron_id' => $sourceContext['squadron_id'] ?? null,
            'source_is_org_owned' => (bool) ($sourceContext['is_org_owned'] ?? false),
            'destination_user_id' => $destinationContext['type'] === 'personal'
                ? ($destinationContext['user_id'] ?? null)
                : null,
            'destination_squadron_id' => $destinationContext['squadron_id'] ?? null,
            'destination_is_org_owned' => (bool) ($destinationContext['is_org_owned'] ?? false),
            'amount' => $attributes['amount'] ?? null,
            'quantity' => $attributes['quantity'] ?? null,
            'currency' => $attributes['currency'] ?? 'aUEC',
            'description' => $attributes['description'] ?? null,
            'transaction_date' => $attributes['transaction_date'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'source_inventory_item_id' => $attributes['source_inventory_item_id'] ?? null,
        ]);
    }

    protected function transferRequiresApproval(User $actor, array $sourceContext, array $destinationContext): bool
    {
        if (
            $sourceContext['type'] === 'personal'
            && $destinationContext['type'] === 'personal'
            && (int) ($sourceContext['user_id'] ?? 0) !== (int) ($destinationContext['user_id'] ?? 0)
        ) {
            return true;
        }

        if (
            $destinationContext['type'] === 'squadron'
            && ($destinationContext['squadron'] ?? null) instanceof Squadron
            && ! $this->contexts->canActorDirectlySendIntoSquadron($actor, $destinationContext['squadron'])
        ) {
            return true;
        }

        return false;
    }

    protected function logPendingTransferRequest(
        User $actor,
        LedgerTransferRequest $transferRequest,
        array $sourceContext,
        array $destinationContext
    ): void {
        $metadata = [
            'amount' => (float) ($transferRequest->amount ?? 0),
            'currency' => $transferRequest->currency ?? 'aUEC',
            'description' => $transferRequest->description,
            'from_label' => $sourceContext['label'],
            'to_label' => $destinationContext['label'],
        ];

        $this->logActivity(
            $actor,
            $sourceContext['subject_user'],
            $transferRequest->wipeCycle,
            'transfer.requested',
            $transferRequest,
            array_merge($metadata, ['direction' => 'outgoing']),
            $sourceContext['squadron'],
            $sourceContext['is_org_owned']
        );

        $this->logActivity(
            $actor,
            $destinationContext['subject_user'],
            $transferRequest->wipeCycle,
            'transfer.requested',
            $transferRequest,
            array_merge($metadata, ['direction' => 'incoming']),
            $destinationContext['squadron'],
            $destinationContext['is_org_owned']
        );
    }

    protected function logPendingInventoryTransferRequest(
        User $actor,
        LedgerTransferRequest $transferRequest,
        array $sourceContext,
        array $destinationContext,
        string $itemLabel,
        float $quantity
    ): void {
        $metadata = [
            'quantity' => $quantity,
            'item_label' => $itemLabel,
            'from_label' => $sourceContext['label'],
            'to_label' => $destinationContext['label'],
        ];

        $this->logActivity(
            $actor,
            $sourceContext['subject_user'],
            $transferRequest->wipeCycle,
            'inventory.transfer_requested',
            $transferRequest,
            array_merge($metadata, ['direction' => 'outgoing']),
            $sourceContext['squadron'],
            $sourceContext['is_org_owned']
        );

        $this->logActivity(
            $actor,
            $destinationContext['subject_user'],
            $transferRequest->wipeCycle,
            'inventory.transfer_requested',
            $transferRequest,
            array_merge($metadata, ['direction' => 'incoming']),
            $destinationContext['squadron'],
            $destinationContext['is_org_owned']
        );
    }

    protected function completeFundTransferRequest(
        User $actor,
        LedgerTransferRequest $transferRequest,
        ?array $sourceContext = null,
        ?array $destinationContext = null
    ): array {
        $sourceContext ??= $this->contexts->transferContextForRequestSource($transferRequest);
        $destinationContext ??= $this->contexts->transferContextForRequestDestination($transferRequest);

        $memo = trim((string) ($transferRequest->description ?? ''));
        $transactionDate = $transferRequest->transaction_date ?? now();
        $amount = round((float) ($transferRequest->amount ?? 0), 2);

        $outgoing = LedgerTransaction::query()->create([
            'user_id' => $sourceContext['user_id'],
            'squadron_id' => $sourceContext['squadron_id'],
            'is_org_owned' => $sourceContext['is_org_owned'],
            'transfer_request_id' => $transferRequest->id,
            'ledger_account_id' => $sourceContext['account']->id,
            'wipe_cycle_id' => $transferRequest->wipe_cycle_id,
            'type' => 'expense',
            'amount' => $amount,
            'currency' => $sourceContext['account']->currency ?? 'aUEC',
            'source_type' => 'transfer',
            'transfer_direction' => 'outgoing',
            'description' => "Transfer to {$destinationContext['label']}: {$memo}",
            'transaction_date' => $transactionDate,
            'notes' => $transferRequest->notes,
        ]);

        $incoming = LedgerTransaction::query()->create([
            'user_id' => $destinationContext['user_id'],
            'squadron_id' => $destinationContext['squadron_id'],
            'is_org_owned' => $destinationContext['is_org_owned'],
            'transfer_request_id' => $transferRequest->id,
            'ledger_account_id' => $destinationContext['account']->id,
            'wipe_cycle_id' => $transferRequest->wipe_cycle_id,
            'type' => 'income',
            'amount' => $amount,
            'currency' => $destinationContext['account']->currency ?? 'aUEC',
            'source_type' => 'transfer',
            'transfer_direction' => 'incoming',
            'description' => "Transfer from {$sourceContext['label']}: {$memo}",
            'transaction_date' => $transactionDate,
            'notes' => $transferRequest->notes,
        ]);

        $transferRequest->forceFill([
            'status' => 'completed',
            'approval_user_id' => $actor->id,
            'approved_at' => now(),
            'completed_at' => now(),
            'outgoing_transaction_id' => $outgoing->id,
            'incoming_transaction_id' => $incoming->id,
        ])->save();

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
            $transferRequest->wipeCycle,
            'transfer.created',
            $outgoing,
            array_merge($metadata, ['direction' => 'outgoing']),
            $sourceContext['squadron'],
            $sourceContext['is_org_owned']
        );

        $this->logActivity(
            $actor,
            $destinationContext['subject_user'],
            $transferRequest->wipeCycle,
            'transfer.created',
            $incoming,
            array_merge($metadata, ['direction' => 'incoming']),
            $destinationContext['squadron'],
            $destinationContext['is_org_owned']
        );

        return [
            'request' => $transferRequest->fresh(),
            'outgoing' => $outgoing,
            'incoming' => $incoming,
        ];
    }

    protected function completeInventoryTransferRequest(
        User $actor,
        LedgerTransferRequest $transferRequest,
        ?array $sourceContext = null,
        ?array $destinationContext = null,
        ?LedgerInventoryItem $sourceItem = null,
        ?float $requestedQuantity = null
    ): array {
        $sourceContext ??= $this->contexts->transferContextForRequestSource($transferRequest);
        $destinationContext ??= $this->contexts->transferContextForRequestDestination($transferRequest);
        $sourceItem ??= $this->resolveInventoryTransferItem(
            $sourceContext,
            (int) $transferRequest->source_inventory_item_id
        );
        $requestedQuantity ??= round((float) ($transferRequest->quantity ?? 0), 4);

        $availableQuantity = round((float) $sourceItem->quantity, 4);

        if ($requestedQuantity <= 0 || $requestedQuantity > $availableQuantity) {
            throw ValidationException::withMessages([
                'quantity' => 'That inventory move can no longer be completed because the available quantity changed.',
            ]);
        }

        $fullTransfer = abs($requestedQuantity - $availableQuantity) < 0.0001;
        $itemLabel = $sourceItem->custom_name
            ?: $this->resolveReferenceLabel($sourceItem->uex_reference_type, $sourceItem->uex_reference_id, $this->references->referenceMaps())
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
                'notes' => $this->appendTransferNote($sourceItem->notes, $transferRequest->notes),
                'transfer_request_id' => $transferRequest->id,
                'provenance_locked' => true,
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
                'transfer_request_id' => $transferRequest->id,
                'provenance_locked' => true,
            ])->save();

            $destinationItem = LedgerInventoryItem::query()->create([
                'user_id' => $destinationContext['user_id'],
                'squadron_id' => $destinationContext['squadron_id'],
                'is_org_owned' => $destinationContext['is_org_owned'],
                'wipe_cycle_id' => $sourceItem->wipe_cycle_id,
                'transfer_request_id' => $transferRequest->id,
                'transfer_origin_item_id' => $sourceItem->transfer_origin_item_id ?: $sourceItem->id,
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
                'provenance_locked' => true,
                'acquired_at' => $sourceItem->acquired_at,
                'notes' => $this->appendTransferNote($sourceItem->notes, $transferRequest->notes),
            ]);
        }

        $transferRequest->forceFill([
            'status' => 'completed',
            'approval_user_id' => $actor->id,
            'approved_at' => now(),
            'completed_at' => now(),
            'destination_inventory_item_id' => $destinationItem->id,
        ])->save();

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
            'request' => $transferRequest->fresh(),
            'source' => $sourceItem->fresh(),
            'destination' => $destinationItem->fresh(),
        ];
    }

    protected function verifiedMemberTransferTargets(User $actor, bool $includeSelf = false): array
    {
        return User::query()
            ->where('global_status', User::STATUS_ACTIVE)
            ->whereNotNull('rsi_verified_at')
            ->when(! $includeSelf, fn ($query) => $query->whereKeyNot($actor->id))
            ->orderByRaw('LOWER(COALESCE(rsi_handle, discord_name, name))')
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => [
                'key' => "personal:{$user->id}",
                'type' => 'personal',
                'user_id' => $user->id,
                'label' => "{$this->contexts->transferDisplayName($user)}'s Assets & Funds",
                'description' => ($includeSelf || (int) $user->id === (int) $actor->id)
                    ? 'Move funds or tracked assets into this verified member\'s personal records.'
                    : 'Move funds or tracked assets into this verified member\'s personal records. They will need to approve it first.',
            ])
            ->values()
            ->all();
    }

    protected function availableSquadronTransferTargets(User $actor, ?Squadron $currentSquadron = null): array
    {
        $activeMembershipIds = Squadron::query()
            ->whereHas('members', function ($query) use ($actor) {
                $query
                    ->where('user_id', $actor->id)
                    ->where('membership_status', 'active');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $manageableIds = collect($this->manageableSquadronTransferTargets($actor))
            ->pluck('squadron_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return Squadron::query()
            ->where('status', 'active')
            ->when($currentSquadron, fn ($query) => $query->whereKeyNot($currentSquadron->id))
            ->orderBy('name')
            ->get()
            ->sortBy(function (Squadron $squadron) use ($activeMembershipIds, $manageableIds) {
                if (in_array((int) $squadron->id, $activeMembershipIds, true)) {
                    return '0-' . mb_strtolower($squadron->name);
                }

                if (in_array((int) $squadron->id, $manageableIds, true)) {
                    return '1-' . mb_strtolower($squadron->name);
                }

                return '2-' . mb_strtolower($squadron->name);
            })
            ->values()
            ->map(function (Squadron $squadron) use ($actor) {
                $requiresApproval = ! $this->contexts->canActorDirectlySendIntoSquadron($actor, $squadron);

                return [
                    'key' => "member-squadron:{$squadron->id}",
                    'type' => 'squadron',
                    'squadron_id' => $squadron->id,
                    'label' => "{$squadron->name} Squadron Assets & Funds",
                    'description' => $requiresApproval
                        ? 'Move funds or tracked assets into this squadron\'s shared records. A squadron ledger manager will need to approve it first.'
                        : 'Move funds or tracked assets into this squadron\'s shared records.',
                ];
            })
            ->values()
            ->all();
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
                'label' => "{$squadron->name} Squadron Assets & Funds",
                'description' => 'Move funds or tracked assets into the shared squadron records.',
            ])
            ->values()
            ->all();
    }

    protected function resolveInventoryTransferItem(array $sourceContext, int $inventoryItemId): LedgerInventoryItem
    {
        $item = LedgerInventoryItem::query()->findOrFail($inventoryItemId);

        return match ($sourceContext['type']) {
            'personal' => tap($item, fn (LedgerInventoryItem $inventoryItem) => $this->personalOwner($sourceContext['subject_user'])->assertOwns($inventoryItem, true)),
            'squadron' => tap($item, fn (LedgerInventoryItem $inventoryItem) => $this->squadronOwner($sourceContext['subject_user'], $sourceContext['squadron'])->assertOwns($inventoryItem, true)),
            'organization' => tap($item, fn (LedgerInventoryItem $inventoryItem) => $this->orgOwner($sourceContext['subject_user'])->assertOwns($inventoryItem, true)),
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

    protected function resolveReferenceLabel(?string $type, $id, array $referenceMaps): ?string
    {
        return $this->references->resolveReferenceLabel($type, $id, $referenceMaps);
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
