<?php

namespace App\Services;

use App\Domain\AccessControl\AccessService;
use App\Models\LedgerActivityLog;
use App\Models\LedgerTransferRequest;
use App\Models\LedgerTransaction;
use App\Models\Squadron;
use App\Models\User;
use App\Models\WipeCycle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LedgerTransferRequestService
{
    public function __construct(
        protected LedgerTransferContextService $contexts,
        protected LedgerReferenceService $references,
        protected AccessService $access,
    ) {}

    public function pendingTransferRequestsForContext(User $viewer, array $context, string $transferKind, array $referenceMaps): array
    {
        return LedgerTransferRequest::query()
            ->where('transfer_kind', $transferKind)
            ->where('status', 'pending')
            ->with([
                'requestedBy:id,name,rsi_handle,discord_name',
                'sourceUser:id,name,rsi_handle,discord_name',
                'destinationUser:id,name,rsi_handle,discord_name',
                'sourceSquadron:id,name',
                'destinationSquadron:id,name',
                'sourceInventoryItem:id,custom_name,uex_reference_type,uex_reference_id',
            ])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (LedgerTransferRequest $request) => $this->transferRequestTouchesContext($request, $context))
            ->take(12)
            ->map(fn (LedgerTransferRequest $request) => $this->presentTransferRequest($request, $viewer, $context, $referenceMaps))
            ->values()
            ->all();
    }

    public function recentFundTransfersForContext(User $viewer, array $context): array
    {
        return LedgerTransferRequest::query()
            ->where('transfer_kind', 'funds')
            ->whereIn('status', ['completed', 'reversed'])
            ->with([
                'requestedBy:id,name,rsi_handle,discord_name',
                'sourceUser:id,name,rsi_handle,discord_name',
                'destinationUser:id,name,rsi_handle,discord_name',
                'sourceSquadron:id,name',
                'destinationSquadron:id,name',
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (LedgerTransferRequest $request) => $this->transferRequestTouchesContext($request, $context))
            ->take(10)
            ->map(fn (LedgerTransferRequest $request) => $this->presentTransferRequest($request, $viewer, $context, []))
            ->values()
            ->all();
    }

    public function approveTransferRequestForContext(
        User $actor,
        LedgerTransferRequest $transferRequest,
        array $context,
        string $transferKind,
        callable $completeFundTransfer,
        callable $completeInventoryTransfer
    ): array {
        return DB::transaction(function () use (
            $actor,
            $transferRequest,
            $context,
            $transferKind,
            $completeFundTransfer,
            $completeInventoryTransfer
        ) {
            $transferRequest = $transferRequest->fresh([
                'wipeCycle',
                'requestedBy',
                'sourceUser',
                'destinationUser',
                'sourceSquadron',
                'destinationSquadron',
                'sourceInventoryItem.wipeCycle',
            ]);

            $this->assertPendingTransferRequestForContext($transferRequest, $context, $transferKind);

            return $transferKind === 'funds'
                ? $completeFundTransfer($actor, $transferRequest)
                : $completeInventoryTransfer($actor, $transferRequest);
        });
    }

    public function rejectTransferRequestForContext(
        User $actor,
        LedgerTransferRequest $transferRequest,
        array $context,
        string $transferKind,
        array $data = []
    ): LedgerTransferRequest {
        return DB::transaction(function () use ($actor, $transferRequest, $context, $transferKind, $data) {
            $transferRequest = $transferRequest->fresh([
                'wipeCycle',
                'sourceUser',
                'destinationUser',
                'sourceSquadron',
                'destinationSquadron',
                'sourceInventoryItem:id,custom_name,uex_reference_type,uex_reference_id',
            ]);

            $this->assertPendingTransferRequestForContext($transferRequest, $context, $transferKind);

            $sourceContext = $this->contexts->transferContextForRequestSource($transferRequest);
            $destinationContext = $this->contexts->transferContextForRequestDestination($transferRequest);
            $referenceMaps = $this->references->referenceMaps();

            $transferRequest->forceFill([
                'status' => 'rejected',
                'approval_user_id' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $data['rejection_reason'] ?? null,
            ])->save();

            $metadata = [
                'description' => $transferRequest->description,
                'from_label' => $sourceContext['label'],
                'to_label' => $destinationContext['label'],
                'item_label' => $transferKind === 'inventory' ? $this->resolveTransferRequestItemLabel($transferRequest, $referenceMaps) : null,
                'quantity' => $transferKind === 'inventory' ? (float) ($transferRequest->quantity ?? 0) : null,
            ];

            $this->logActivity(
                $actor,
                $sourceContext['subject_user'],
                $transferRequest->wipeCycle,
                $transferKind === 'funds' ? 'transfer.rejected' : 'inventory.transfer_rejected',
                $transferRequest,
                $metadata,
                $sourceContext['squadron'],
                $sourceContext['is_org_owned']
            );

            $this->logActivity(
                $actor,
                $destinationContext['subject_user'],
                $transferRequest->wipeCycle,
                $transferKind === 'funds' ? 'transfer.rejected' : 'inventory.transfer_rejected',
                $transferRequest,
                $metadata,
                $destinationContext['squadron'],
                $destinationContext['is_org_owned']
            );

            return $transferRequest->fresh();
        });
    }

    public function reverseCompletedFundTransferForContext(
        User $actor,
        LedgerTransferRequest $transferRequest,
        array $context,
        array $data = []
    ): array {
        return DB::transaction(function () use ($actor, $transferRequest, $context, $data) {
            $transferRequest = $transferRequest->fresh([
                'wipeCycle',
                'requestedBy',
                'sourceUser',
                'destinationUser',
                'sourceSquadron',
                'destinationSquadron',
                'outgoingTransaction',
                'incomingTransaction',
            ]);

            $this->assertCompletedFundTransferCanBeReversed($actor, $transferRequest, $context);

            $sourceContext = $this->contexts->transferContextForRequestSource($transferRequest);
            $destinationContext = $this->contexts->transferContextForRequestDestination($transferRequest);
            $amount = round((float) ($transferRequest->amount ?? 0), 2);
            $memo = trim((string) ($transferRequest->description ?? ''));
            $reversalDate = now();
            $reversalNotes = $data['reversal_notes'] ?? null;

            $sourceReversal = LedgerTransaction::query()->create([
                'user_id' => $sourceContext['user_id'],
                'squadron_id' => $sourceContext['squadron_id'],
                'is_org_owned' => $sourceContext['is_org_owned'],
                'transfer_request_id' => $transferRequest->id,
                'reversal_of_transaction_id' => $transferRequest->outgoing_transaction_id,
                'ledger_account_id' => $sourceContext['account']->id,
                'wipe_cycle_id' => $transferRequest->wipe_cycle_id,
                'type' => 'income',
                'amount' => $amount,
                'currency' => $sourceContext['account']->currency ?? 'aUEC',
                'source_type' => 'transfer_reversal',
                'transfer_direction' => 'reversal',
                'description' => "Transfer reversal from {$destinationContext['label']}: {$memo}",
                'transaction_date' => $reversalDate,
                'notes' => $reversalNotes,
            ]);

            $destinationReversal = LedgerTransaction::query()->create([
                'user_id' => $destinationContext['user_id'],
                'squadron_id' => $destinationContext['squadron_id'],
                'is_org_owned' => $destinationContext['is_org_owned'],
                'transfer_request_id' => $transferRequest->id,
                'reversal_of_transaction_id' => $transferRequest->incoming_transaction_id,
                'ledger_account_id' => $destinationContext['account']->id,
                'wipe_cycle_id' => $transferRequest->wipe_cycle_id,
                'type' => 'expense',
                'amount' => $amount,
                'currency' => $destinationContext['account']->currency ?? 'aUEC',
                'source_type' => 'transfer_reversal',
                'transfer_direction' => 'reversal',
                'description' => "Transfer reversal to {$sourceContext['label']}: {$memo}",
                'transaction_date' => $reversalDate,
                'notes' => $reversalNotes,
            ]);

            $transferRequest->forceFill([
                'status' => 'reversed',
                'reversal_user_id' => $actor->id,
                'reversal_notes' => $reversalNotes,
                'reversed_at' => now(),
                'reversal_outgoing_transaction_id' => $sourceReversal->id,
                'reversal_incoming_transaction_id' => $destinationReversal->id,
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
                'transfer.reversed',
                $sourceReversal,
                array_merge($metadata, ['direction' => 'source']),
                $sourceContext['squadron'],
                $sourceContext['is_org_owned']
            );

            $this->logActivity(
                $actor,
                $destinationContext['subject_user'],
                $transferRequest->wipeCycle,
                'transfer.reversed',
                $destinationReversal,
                array_merge($metadata, ['direction' => 'destination']),
                $destinationContext['squadron'],
                $destinationContext['is_org_owned']
            );

            return [
                'request' => $transferRequest->fresh(),
                'source_reversal' => $sourceReversal,
                'destination_reversal' => $destinationReversal,
            ];
        });
    }

    protected function presentTransferRequest(
        LedgerTransferRequest $request,
        User $viewer,
        array $context,
        array $referenceMaps
    ): array {
        $direction = $this->transferRequestDirectionForContext($request, $context);

        return [
            'id' => $request->id,
            'kind' => $request->transfer_kind,
            'status' => $request->status,
            'direction' => $direction,
            'from_label' => $this->transferRequestLabelForColumns(
                $request->source_is_org_owned,
                $request->source_squadron_id,
                $request->sourceUser,
                $request->sourceSquadron
            ),
            'to_label' => $this->transferRequestLabelForColumns(
                $request->destination_is_org_owned,
                $request->destination_squadron_id,
                $request->destinationUser,
                $request->destinationSquadron
            ),
            'requested_by_name' => $request->requestedBy
                ? $this->contexts->transferDisplayName($request->requestedBy)
                : "Member {$request->requested_by_user_id}",
            'description' => $request->description,
            'item_label' => $request->transfer_kind === 'inventory'
                ? $this->resolveTransferRequestItemLabel($request, $referenceMaps)
                : null,
            'amount' => $request->amount !== null ? (float) $request->amount : null,
            'quantity' => $request->quantity !== null ? (float) $request->quantity : null,
            'currency' => $request->currency ?? 'aUEC',
            'notes' => $request->notes,
            'transaction_date' => $request->transaction_date?->toIso8601String(),
            'created_at' => $request->created_at?->toIso8601String(),
            'completed_at' => $request->completed_at?->toIso8601String(),
            'reversed_at' => $request->reversed_at?->toIso8601String(),
            'can_approve' => $request->status === 'pending' && $this->canApproveTransferRequestForContext($viewer, $request, $context),
            'can_reject' => $request->status === 'pending' && $this->canApproveTransferRequestForContext($viewer, $request, $context),
            'can_reverse' => $request->transfer_kind === 'funds'
                && $request->status === 'completed'
                && $this->canReverseTransferRequestForContext($viewer, $request, $context),
        ];
    }

    protected function resolveTransferRequestItemLabel(LedgerTransferRequest $request, array $referenceMaps = []): string
    {
        if ($request->description) {
            return $request->description;
        }

        $sourceItem = $request->sourceInventoryItem;

        if (! $sourceItem) {
            return 'Inventory item';
        }

        return $sourceItem->custom_name
            ?: $this->references->resolveReferenceLabel($sourceItem->uex_reference_type, $sourceItem->uex_reference_id, $referenceMaps)
            ?: 'Inventory item';
    }

    protected function transferRequestLabelForColumns(
        bool $isOrgOwned,
        ?int $squadronId,
        ?User $user,
        ?Squadron $squadron
    ): string {
        if ($isOrgOwned) {
            return 'Horizon Treasury';
        }

        if ($squadronId) {
            return ($squadron?->name ?? "Squadron {$squadronId}") . ' Squadron Assets & Funds';
        }

        if ($user) {
            return "{$this->contexts->transferDisplayName($user)}'s Assets & Funds";
        }

        return 'Personal Assets & Funds';
    }

    protected function transferRequestDirectionForContext(LedgerTransferRequest $request, array $context): string
    {
        return $this->transferRequestContextMatches($request, $context, 'destination')
            ? 'incoming'
            : 'outgoing';
    }

    protected function transferRequestTouchesContext(LedgerTransferRequest $request, array $context): bool
    {
        return $this->transferRequestContextMatches($request, $context, 'source')
            || $this->transferRequestContextMatches($request, $context, 'destination');
    }

    protected function transferRequestContextMatches(LedgerTransferRequest $request, array $context, string $prefix): bool
    {
        $squadronId = (int) ($context['squadron_id'] ?? 0);
        $userId = (int) ($context['user_id'] ?? 0);
        $isOrgOwned = (bool) ($context['is_org_owned'] ?? false);

        if ($isOrgOwned) {
            return (bool) $request->getAttribute("{$prefix}_is_org_owned");
        }

        if ($squadronId > 0) {
            return (int) $request->getAttribute("{$prefix}_squadron_id") === $squadronId
                && ! (bool) $request->getAttribute("{$prefix}_is_org_owned");
        }

        return (int) $request->getAttribute("{$prefix}_user_id") === $userId
            && blank($request->getAttribute("{$prefix}_squadron_id"))
            && ! (bool) $request->getAttribute("{$prefix}_is_org_owned");
    }

    protected function canApproveTransferRequestForContext(User $viewer, LedgerTransferRequest $request, array $context): bool
    {
        if (! $this->transferRequestContextMatches($request, $context, 'destination')) {
            return false;
        }

        return match ($context['type']) {
            'personal' => (int) ($context['user_id'] ?? 0) === (int) $viewer->id,
            'squadron' => ($context['squadron'] ?? null) instanceof Squadron
                && $this->access->canManageSquadronLedger($viewer, $context['squadron']),
            'organization' => $this->contexts->canManageOrganizationLedger($viewer),
            default => false,
        };
    }

    protected function canReverseTransferRequestForContext(User $viewer, LedgerTransferRequest $request, array $context): bool
    {
        if (! $this->transferRequestTouchesContext($request, $context)) {
            return false;
        }

        return match ($context['type']) {
            'personal' => (int) ($context['user_id'] ?? 0) === (int) $viewer->id,
            'squadron' => ($context['squadron'] ?? null) instanceof Squadron
                && $this->access->canManageSquadronLedger($viewer, $context['squadron']),
            'organization' => $this->contexts->canManageOrganizationLedger($viewer),
            default => false,
        };
    }

    protected function assertPendingTransferRequestForContext(
        LedgerTransferRequest $transferRequest,
        array $context,
        string $transferKind
    ): void {
        if ($transferRequest->transfer_kind !== $transferKind || $transferRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'transfer' => 'That transfer request is no longer waiting for approval.',
            ]);
        }

        if (! $this->transferRequestContextMatches($transferRequest, $context, 'destination')) {
            throw ValidationException::withMessages([
                'transfer' => 'That transfer request does not belong to this ledger.',
            ]);
        }
    }

    protected function assertCompletedFundTransferCanBeReversed(
        User $actor,
        LedgerTransferRequest $transferRequest,
        array $context
    ): void {
        if ($transferRequest->transfer_kind !== 'funds' || $transferRequest->status !== 'completed') {
            throw ValidationException::withMessages([
                'transfer' => 'Only completed fund transfers can be reversed.',
            ]);
        }

        if (! $this->transferRequestTouchesContext($transferRequest, $context)) {
            throw ValidationException::withMessages([
                'transfer' => 'That transfer does not belong to this ledger.',
            ]);
        }

        if (! $this->canReverseTransferRequestForContext($actor, $transferRequest, $context)) {
            throw ValidationException::withMessages([
                'transfer' => 'You do not have permission to reverse that transfer.',
            ]);
        }

        $wipeCycle = $transferRequest->wipeCycle;

        if ($wipeCycle && ! $wipeCycle->is_current) {
            throw ValidationException::withMessages([
                'wipe_cycle_id' => 'Archived cycles are read only. Completed transfers can only be reversed while their cycle is still current.',
            ]);
        }
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
