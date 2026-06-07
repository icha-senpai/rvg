<?php

namespace App\Domain\Operations\Services;

use App\Models\LedgerInventoryItem;
use App\Models\LedgerTransferRequest;
use App\Models\LedgerTransaction;
use App\Models\Operation;
use App\Models\OperationSettlement;
use App\Models\Squadron;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OperationSettlementService
{
    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function upsertDraft(User $actor, Operation $operation, array $data): OperationSettlement
    {
        $this->assertEditable($operation);

        return DB::transaction(function () use ($operation, $data) {
            $settlement = $this->firstOrNewSettlement($operation);
            $payload = $this->normalizeSettlementPayload($operation, $data, false);

            $settlement->forceFill([
                'money_rows' => $payload['money_rows'],
                'loot_rows' => $payload['loot_rows'],
            ])->save();

            return $settlement->fresh();
        });
    }

    public function finalize(User $actor, Operation $operation, array $data = []): OperationSettlement
    {
        $this->assertEditable($operation);

        return DB::transaction(function () use ($actor, $operation, $data) {
            $settlement = $this->firstOrNewSettlement($operation);
            $payloadSource = array_key_exists('money_rows', $data) || array_key_exists('loot_rows', $data)
                ? $data
                : [
                    'money_rows' => $settlement->money_rows ?? [],
                    'loot_rows' => $settlement->loot_rows ?? [],
                ];
            $payload = $this->normalizeSettlementPayload($operation, $payloadSource, true);

            $settlement->forceFill([
                'money_rows' => $payload['money_rows'],
                'loot_rows' => $payload['loot_rows'],
                'finalized_by_user_id' => $actor->id,
                'finalized_at' => now(),
            ])->save();

            $this->createSettlementReceipts($actor, $operation->fresh(['squadron']), $settlement);

            return $settlement->fresh();
        });
    }

    public function reopen(User $actor, Operation $operation): OperationSettlement
    {
        if (! $operation->isCompleted()) {
            throw ValidationException::withMessages([
                'operation' => 'Only completed operations can use settlements.',
            ]);
        }

        $settlement = $operation->relationLoaded('settlement')
            ? $operation->getRelation('settlement')
            : $operation->settlement()->first();

        if (! $settlement) {
            throw ValidationException::withMessages([
                'settlement' => 'There is no operation settlement to reopen yet.',
            ]);
        }

        return DB::transaction(function () use ($actor, $settlement) {
            $this->assertReopenSafe($settlement);
            $this->deleteSettlementReceipts($settlement);

            $settlement->forceFill([
                'finalized_by_user_id' => null,
                'finalized_at' => null,
                'reopened_by_user_id' => $actor->id,
                'reopened_at' => now(),
            ])->save();

            return $settlement->fresh();
        });
    }

    protected function firstOrNewSettlement(Operation $operation): OperationSettlement
    {
        return $operation->relationLoaded('settlement')
            ? ($operation->getRelation('settlement') ?? new OperationSettlement(['operation_id' => $operation->id]))
            : OperationSettlement::query()->firstOrNew([
                'operation_id' => $operation->id,
            ]);
    }

    protected function assertEditable(Operation $operation): void
    {
        if (! $operation->isCompleted()) {
            throw ValidationException::withMessages([
                'operation' => 'Only completed operations can use settlements.',
            ]);
        }

        if ($this->isFinalized($operation)) {
            throw ValidationException::withMessages([
                'settlement' => 'This settlement is finalized. Reopen it before making changes.',
            ]);
        }
    }

    protected function isFinalized(Operation $operation): bool
    {
        $settlement = $operation->relationLoaded('settlement')
            ? $operation->getRelation('settlement')
            : $operation->settlement()->first();

        return (bool) $settlement?->finalized_at;
    }

    protected function normalizeSettlementPayload(Operation $operation, array $data, bool $strict): array
    {
        $eligibleMemberIds = $this->normalizeUserIds($operation->after_action_attendance_user_ids ?? []);
        $eligibleSquadronIds = $this->settlementEligibleSquadronIds($operation);
        $itemNames = $this->uexItemNames();
        $commodityNames = $this->uexCommodityNames();

        return [
            'money_rows' => collect($data['money_rows'] ?? [])
                ->map(fn ($row) => $this->normalizeSettlementMoneyRow(
                    $row,
                    $eligibleMemberIds,
                    $eligibleSquadronIds,
                    $strict
                ))
                ->filter()
                ->values()
                ->all(),
            'loot_rows' => collect($data['loot_rows'] ?? [])
                ->map(fn ($row) => $this->normalizeSettlementLootRow(
                    $row,
                    $eligibleMemberIds,
                    $eligibleSquadronIds,
                    $itemNames,
                    $commodityNames,
                    $strict
                ))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    protected function normalizeSettlementMoneyRow($row, array $eligibleMemberIds, array $eligibleSquadronIds, bool $strict): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        $recipientType = $this->normalizeSettlementRecipientType($row['recipient_type'] ?? null);
        $recipientUserId = filled($row['recipient_user_id'] ?? null) ? (int) $row['recipient_user_id'] : null;
        $recipientSquadronId = filled($row['recipient_squadron_id'] ?? null) ? (int) $row['recipient_squadron_id'] : null;
        $amount = filled($row['amount'] ?? null) ? round((float) $row['amount'], 2) : null;
        $notes = $this->nullableTrimmedString($row['notes'] ?? null);

        if ($recipientType === null && $recipientUserId === null && $recipientSquadronId === null && $amount === null && $notes === null) {
            return null;
        }

        if ($strict) {
            if (! $recipientType) {
                throw ValidationException::withMessages([
                    'money_rows' => 'Each payout row needs a destination before the settlement can be finalized.',
                ]);
            }

            $this->assertSettlementRecipientIsEligible(
                $recipientType,
                $recipientUserId,
                $recipientSquadronId,
                $eligibleMemberIds,
                $eligibleSquadronIds
            );

            if ($amount === null || $amount <= 0) {
                throw ValidationException::withMessages([
                    'money_rows' => 'Each payout row needs a positive amount before the settlement can be finalized.',
                ]);
            }
        }

        return [
            'row_key' => $this->normalizeSettlementRowKey($row['row_key'] ?? null),
            'recipient_type' => $recipientType,
            'recipient_user_id' => $recipientUserId,
            'recipient_squadron_id' => $recipientType === 'squadron' ? $recipientSquadronId : null,
            'amount' => $amount,
            'notes' => $notes,
        ];
    }

    protected function normalizeSettlementLootRow(
        $row,
        array $eligibleMemberIds,
        array $eligibleSquadronIds,
        array $itemNames,
        array $commodityNames,
        bool $strict
    ): ?array {
        if (! is_array($row)) {
            return null;
        }

        $sourceType = $this->normalizeSettlementLootSourceType($row['source_type'] ?? null);
        $recipientType = $this->normalizeSettlementRecipientType($row['recipient_type'] ?? null);
        $recipientUserId = filled($row['recipient_user_id'] ?? null) ? (int) $row['recipient_user_id'] : null;
        $recipientSquadronId = filled($row['recipient_squadron_id'] ?? null) ? (int) $row['recipient_squadron_id'] : null;
        $referenceId = filled($row['uex_reference_id'] ?? null) ? (int) $row['uex_reference_id'] : null;
        $quantity = filled($row['quantity'] ?? null) ? round((float) $row['quantity'], 4) : null;
        $unitLabel = $this->nullableTrimmedString($row['unit_label'] ?? null);
        $notes = $this->nullableTrimmedString($row['notes'] ?? null);

        if ($sourceType === null && $recipientType === null && $recipientUserId === null && $recipientSquadronId === null && $referenceId === null && $quantity === null && $unitLabel === null && $notes === null) {
            return null;
        }

        $referenceLabel = null;

        if ($sourceType === 'commodity' && $referenceId) {
            $referenceLabel = $commodityNames[$referenceId] ?? null;
        } elseif (in_array($sourceType, ['item', 'component'], true) && $referenceId) {
            $referenceLabel = $itemNames[$referenceId] ?? null;
        }

        if ($strict) {
            if (! $sourceType) {
                throw ValidationException::withMessages([
                    'loot_rows' => 'Each loot row needs a type before the settlement can be finalized.',
                ]);
            }

            if (! $referenceId || ! $referenceLabel) {
                throw ValidationException::withMessages([
                    'loot_rows' => 'Each loot row needs a valid synced UEX item, component, or commodity before the settlement can be finalized.',
                ]);
            }

            if ($quantity === null || $quantity <= 0) {
                throw ValidationException::withMessages([
                    'loot_rows' => 'Each loot row needs a positive quantity before the settlement can be finalized.',
                ]);
            }

            if (! $recipientType) {
                throw ValidationException::withMessages([
                    'loot_rows' => 'Each loot row needs a destination before the settlement can be finalized.',
                ]);
            }

            $this->assertSettlementRecipientIsEligible(
                $recipientType,
                $recipientUserId,
                $recipientSquadronId,
                $eligibleMemberIds,
                $eligibleSquadronIds
            );
        }

        return [
            'row_key' => $this->normalizeSettlementRowKey($row['row_key'] ?? null),
            'source_type' => $sourceType,
            'uex_reference_type' => $sourceType === 'commodity' ? 'commodity' : ($sourceType ? 'item' : null),
            'uex_reference_id' => $referenceId,
            'reference_label' => $referenceLabel,
            'recipient_type' => $recipientType,
            'recipient_user_id' => $recipientUserId,
            'recipient_squadron_id' => $recipientType === 'squadron' ? $recipientSquadronId : null,
            'quantity' => $quantity,
            'unit_label' => $unitLabel,
            'notes' => $notes,
        ];
    }

    protected function assertSettlementRecipientIsEligible(
        ?string $recipientType,
        ?int $recipientUserId,
        ?int $recipientSquadronId,
        array $eligibleMemberIds,
        array $eligibleSquadronIds
    ): void {
        if ($recipientType === 'member') {
            if (! $recipientUserId || ! in_array($recipientUserId, $eligibleMemberIds, true)) {
                throw ValidationException::withMessages([
                    'recipient_user_id' => 'Member settlement destinations must come from the final attendance roster.',
                ]);
            }

            return;
        }

        if ($recipientType === 'squadron') {
            if (empty($eligibleSquadronIds)) {
                throw ValidationException::withMessages([
                    'recipient_type' => 'Only squadron operations can settle into a squadron ledger.',
                ]);
            }

            if (! $recipientSquadronId || ! in_array($recipientSquadronId, $eligibleSquadronIds, true)) {
                throw ValidationException::withMessages([
                    'recipient_squadron_id' => 'Squadron settlement destinations must come from the operation squadron list.',
                ]);
            }
        }
    }

    protected function createSettlementReceipts(User $actor, Operation $operation, OperationSettlement $settlement): void
    {
        foreach ($settlement->money_rows ?? [] as $row) {
            $this->createSettlementMoneyReceipt($actor, $operation, $settlement, $row);
        }

        foreach ($settlement->loot_rows ?? [] as $row) {
            $this->createSettlementLootReceipt($actor, $operation, $settlement, $row);
        }
    }

    protected function createSettlementMoneyReceipt(User $actor, Operation $operation, OperationSettlement $settlement, array $row): void
    {
        $description = sprintf(
            'Operation settlement payout · %s',
            $operation->title ?: "Operation #{$operation->id}"
        );
        $payload = [
            'type' => 'income',
            'amount' => $row['amount'],
            'currency' => 'aUEC',
            'source_type' => 'operation_settlement',
            'description' => $description,
            'transaction_date' => $operation->ends_at ?? now(),
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'provenance_locked' => true,
            'notes' => $row['notes'] ?? null,
        ];

        match ($row['recipient_type']) {
            'member' => $this->ledger->createTransaction(
                $actor,
                User::query()->findOrFail((int) $row['recipient_user_id']),
                $payload
            ),
            'squadron' => $this->ledger->createSquadronTransaction(
                $actor,
                Squadron::query()->findOrFail((int) $row['recipient_squadron_id']),
                $payload
            ),
            'organization' => $this->ledger->createOrgTransaction($actor, $payload),
            default => null,
        };
    }

    protected function createSettlementLootReceipt(User $actor, Operation $operation, OperationSettlement $settlement, array $row): void
    {
        $payload = [
            'source_type' => $row['source_type'],
            'uex_reference_type' => $row['uex_reference_type'],
            'uex_reference_id' => $row['uex_reference_id'],
            'category' => match ($row['source_type']) {
                'commodity' => 'Commodity',
                'component' => 'Component',
                default => 'Item',
            },
            'quantity' => $row['quantity'],
            'unit_label' => $row['unit_label'] ?: ($row['source_type'] === 'commodity' ? 'SCU' : 'units'),
            'location_name' => $operation->title ?: "Operation #{$operation->id}",
            'purchase_price' => null,
            'estimated_value' => null,
            'currency' => 'aUEC',
            'status' => 'stored',
            'provenance_locked' => true,
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'acquired_at' => $operation->ends_at ?? now(),
            'notes' => $row['notes'] ?? null,
        ];

        match ($row['recipient_type']) {
            'member' => $this->ledger->createInventoryItem(
                $actor,
                User::query()->findOrFail((int) $row['recipient_user_id']),
                $payload
            ),
            'squadron' => $this->ledger->createSquadronInventoryItem(
                $actor,
                Squadron::query()->findOrFail((int) $row['recipient_squadron_id']),
                $payload
            ),
            'organization' => $this->ledger->createOrgInventoryItem($actor, $payload),
            default => null,
        };
    }

    protected function assertReopenSafe(OperationSettlement $settlement): void
    {
        if ($this->settlementInventoryHasTransferDescendants($settlement)) {
            throw ValidationException::withMessages([
                'settlement' => 'This settlement cannot be reopened because some of its loot receipts have already been moved into other ledgers. Those downstream inventory moves need to stay intact.',
            ]);
        }

        if ($this->settlementFundsHaveDownstreamTransferActivity($settlement)) {
            throw ValidationException::withMessages([
                'settlement' => 'This settlement cannot be reopened because one of the receiving ledgers already started moving funds after the payout landed. Reopening now could break later transfer history.',
            ]);
        }
    }

    protected function settlementInventoryHasTransferDescendants(OperationSettlement $settlement): bool
    {
        $settlementItemIds = LedgerInventoryItem::query()
            ->where('operation_settlement_id', $settlement->id)
            ->pluck('id');

        if ($settlementItemIds->isEmpty()) {
            return false;
        }

        return LedgerInventoryItem::query()
            ->where(function ($query) use ($settlementItemIds) {
                $query->whereIn('id', $settlementItemIds)
                    ->whereNotNull('transfer_request_id');
            })
            ->orWhereIn('transfer_origin_item_id', $settlementItemIds)
            ->exists();
    }

    protected function settlementFundsHaveDownstreamTransferActivity(OperationSettlement $settlement): bool
    {
        if (! $settlement->finalized_at) {
            return false;
        }

        $contexts = LedgerTransaction::query()
            ->where('operation_settlement_id', $settlement->id)
            ->get(['user_id', 'squadron_id', 'is_org_owned'])
            ->map(function (LedgerTransaction $transaction) {
                if ((bool) $transaction->is_org_owned) {
                    return [
                        'type' => 'organization',
                    ];
                }

                if ($transaction->squadron_id) {
                    return [
                        'type' => 'squadron',
                        'squadron_id' => (int) $transaction->squadron_id,
                    ];
                }

                return [
                    'type' => 'personal',
                    'user_id' => (int) $transaction->user_id,
                ];
            })
            ->unique(fn (array $context) => json_encode($context))
            ->values();

        foreach ($contexts as $context) {
            $query = LedgerTransferRequest::query()
                ->where('transfer_kind', 'funds')
                ->where('status', '!=', 'rejected')
                ->where('created_at', '>=', $settlement->finalized_at);

            match ($context['type']) {
                'organization' => $query
                    ->where('source_is_org_owned', true)
                    ->whereNull('source_squadron_id'),
                'squadron' => $query
                    ->where('source_squadron_id', $context['squadron_id'])
                    ->where('source_is_org_owned', false),
                'personal' => $query
                    ->where('source_user_id', $context['user_id'])
                    ->whereNull('source_squadron_id')
                    ->where('source_is_org_owned', false),
            };

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    protected function deleteSettlementReceipts(OperationSettlement $settlement): void
    {
        LedgerInventoryItem::query()
            ->where('operation_settlement_id', $settlement->id)
            ->delete();

        LedgerTransaction::query()
            ->where('operation_settlement_id', $settlement->id)
            ->delete();
    }

    protected function settlementEligibleSquadronIds(Operation $operation): array
    {
        $names = collect(explode(',', (string) ($operation->squadron_name ?? '')))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->values();

        $ids = collect();

        if ($operation->squadron_id) {
            $ids->push((int) $operation->squadron_id);
        }

        if ($names->isNotEmpty()) {
            $ids = $ids->merge(
                Squadron::query()
                    ->whereIn('name', $names->all())
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
            );
        }

        return $ids
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function uexItemNames(): array
    {
        return DB::table('uex_items')
            ->pluck('name', 'uex_id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name ?: "Item {$id}"])
            ->all();
    }

    protected function uexCommodityNames(): array
    {
        return DB::table('uex_commodities')
            ->pluck('name', 'uex_id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name ?: "Commodity {$id}"])
            ->all();
    }

    protected function normalizeSettlementRowKey($value): string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : (string) Str::uuid();
    }

    protected function normalizeSettlementRecipientType($value): ?string
    {
        $normalized = trim(strtolower((string) ($value ?? '')));

        return in_array($normalized, ['member', 'squadron', 'organization'], true)
            ? $normalized
            : null;
    }

    protected function normalizeSettlementLootSourceType($value): ?string
    {
        $normalized = trim(strtolower((string) ($value ?? '')));

        return in_array($normalized, ['item', 'component', 'commodity'], true)
            ? $normalized
            : null;
    }

    protected function nullableTrimmedString($value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeUserIds(array $userIds): array
    {
        return collect($userIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
