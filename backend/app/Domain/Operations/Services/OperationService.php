<?php

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Actions\CancelOperation;
use App\Domain\Operations\Actions\CreateOperation;
use App\Domain\Operations\Actions\TransitionOperation;
use App\Domain\Operations\Actions\UpdateOperation;
use App\Domain\Operations\Enums\CompletionOutcome;
use App\Domain\Operations\Enums\OperationStatus;
use App\Models\LedgerInventoryItem;
use App\Models\LedgerTransferRequest;
use App\Models\LedgerTransaction;
use App\Models\Operation;
use App\Models\OperationSettlement;
use App\Models\OperationRole;
use App\Models\Squadron;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Coordinates operation actions while keeping shared normalization and error
 * handling rules in one domain service.
 */
class OperationService
{
    public function __construct(
        protected OperationMediaService $operationMedia,
        protected LedgerService $ledger,
    ) {}

    /**
     * Create a new operation after applying shared defaults to the payload.
     */
    public function create(array $data, ?Squadron $squadron = null): Operation
    {
        [$data, $mediaId, $shouldSyncMedia] = $this->pullMediaId(
            $this->applyDefaults($data)
        );

        $operation = (new CreateOperation)->execute($data, $squadron);
        $this->syncRoles($operation, $data);

        if ($shouldSyncMedia) {
            $this->operationMedia->syncOperationImage($operation, $mediaId);
        }

        return $operation;
    }

    /**
     * Update an existing operation after applying the same shared defaults used
     * during creation.
     */
    public function update(Operation $operation, array $data): Operation
    {
        [$data, $mediaId, $shouldSyncMedia] = $this->pullMediaId(
            $this->applyDefaults($data)
        );

        $operation = (new UpdateOperation)->execute($operation, $data);
        $this->syncRoles($operation, $data);

        if ($shouldSyncMedia) {
            $this->operationMedia->syncOperationImage($operation, $mediaId);
        }

        return $operation;
    }

    /**
     * Cancel an operation through the dedicated cancel action.
     */
    public function cancel(Operation $operation, ?string $reason = null): Operation
    {
        return (new CancelOperation)->execute($operation, $reason);
    }

    /**
     * Transition an operation through the state machine and re-map low-level
     * transition errors into a validation-style response shape.
     */
    public function transition(Operation $operation, string $status, ?string $reason = null, ?string $outcome = null): Operation
    {
        try {
            $updated = (new TransitionOperation)->execute($operation, $status, $reason, $outcome);

            return $this->ensureAfterActionDefaults($updated, $status);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'status' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Apply shared defaults so create and update flows behave the same way when
     * optional fields are omitted.
     */
    protected function applyDefaults(array $data): array
    {
        $data['visibility'] = $data['visibility'] ?? 'open';

        // Empty slot payloads are normalized to an array so downstream actions do
        // not have to branch on null, empty string, or missing input.
        if (array_key_exists('slots', $data) && empty($data['slots'])) {
            $data['slots'] = [];
        }

        if (array_key_exists('roles', $data)) {
            $data['roles'] = collect(is_array($data['roles']) ? $data['roles'] : [])
                ->map(function ($role) {
                    if (! is_array($role)) {
                        return null;
                    }

                    $displayName = trim((string) ($role['role_display_name'] ?? ''));
                    if ($displayName === '') {
                        return null;
                    }

                    $capacity = $role['capacity'] ?? null;

                    return [
                        'id' => $role['id'] ?? null,
                        'role_name' => trim((string) ($role['role_name'] ?? '')),
                        'role_display_name' => $displayName,
                        'capacity' => $capacity === '' || $capacity === null ? null : (int) $capacity,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            $data['slots'] = collect($data['roles'])
                ->pluck('role_display_name')
                ->values()
                ->all();
        }

        return $data;
    }

    protected function syncRoles(Operation $operation, array $data): void
    {
        if (! array_key_exists('roles', $data)) {
            return;
        }

        $incomingRoles = collect($data['roles'] ?? []);
        $existingRoles = $operation->roles()->get()->keyBy('id');
        $keptRoleIds = [];

        foreach ($incomingRoles as $index => $role) {
            $roleId = isset($role['id']) ? (int) $role['id'] : null;
            $roleName = trim((string) ($role['role_name'] ?? ''));
            $displayName = trim((string) ($role['role_display_name'] ?? ''));

            if ($displayName === '') {
                continue;
            }

            $payload = [
                'role_name' => $roleName !== '' ? $roleName : str($displayName)->lower()->slug('_')->value(),
                'role_display_name' => $displayName,
                'capacity' => $role['capacity'] ?? null,
            ];

            if ($roleId && $existingRoles->has($roleId)) {
                /** @var OperationRole $existingRole */
                $existingRole = $existingRoles->get($roleId);
                $previousDisplayName = $existingRole->role_display_name;
                $existingRole->fill($payload)->save();
                $keptRoleIds[] = $existingRole->id;

                if ($previousDisplayName !== $displayName) {
                    $operation->participants()
                        ->where('operation_role_id', $existingRole->id)
                        ->update(['slot' => $displayName]);
                }

                continue;
            }

            $createdRole = $operation->roles()->create($payload);
            $keptRoleIds[] = $createdRole->id;
        }

        $rolesToDelete = $existingRoles
            ->keys()
            ->reject(fn ($id) => in_array((int) $id, $keptRoleIds, true));

        if ($rolesToDelete->isNotEmpty()) {
            $operation->participants()
                ->whereIn('operation_role_id', $rolesToDelete->all())
                ->update([
                    'operation_role_id' => null,
                    'slot' => null,
                ]);

            $operation->roles()->whereIn('id', $rolesToDelete->all())->delete();
        }
    }

    /**
     * Load the related records needed by full operation presenters and detail
     * screens.
     */
    public function loadGraph(Operation $operation): Operation
    {
        return $operation->load([
            'squadron',
            'creator',
            'creator.roles',
            'participants.user',
            'roles.participants.user',
        ]);
    }

    /**
     * Persist the editable after action report body and the final attendance
     * roster captured for a completed operation.
     */
    public function updateAfterActionReport(Operation $operation, ?string $report, array $attendanceUserIds = [], array $noShowUserIds = []): Operation
    {
        if (! $operation->isCompleted()) {
            throw ValidationException::withMessages([
                'after_action_report' => 'After Action Reports are only available for completed operations.',
            ]);
        }

        $previousAttendanceUserIds = $this->normalizeUserIds($operation->after_action_attendance_user_ids ?? []);
        $previousNoShowUserIds = $this->normalizeUserIds($operation->after_action_no_show_user_ids ?? []);

        $normalizedAttendanceUserIds = $this->normalizeUserIds($attendanceUserIds);
        $normalizedNoShowUserIds = collect($this->normalizeUserIds($noShowUserIds))
            ->reject(fn ($id) => in_array($id, $normalizedAttendanceUserIds, true))
            ->values()
            ->all();

        if (
            $this->settlementIsFinalized($operation)
            && (
                $normalizedAttendanceUserIds !== $previousAttendanceUserIds
                || $normalizedNoShowUserIds !== $previousNoShowUserIds
            )
        ) {
            throw ValidationException::withMessages([
                'attendance_user_ids' => 'Final attendance is locked once the operation settlement is finalized. Reopen the settlement to change attendance.',
            ]);
        }

        $operation->forceFill([
            'after_action_report' => filled($report) ? trim((string) $report) : null,
            'after_action_attendance_user_ids' => $normalizedAttendanceUserIds,
            'after_action_no_show_user_ids' => $normalizedNoShowUserIds,
            'after_action_report_updated_at' => now(),
        ])->save();

        $this->recalculateAfterActionStatsForUsers(array_merge(
            $previousAttendanceUserIds,
            $previousNoShowUserIds,
            $normalizedAttendanceUserIds,
            $normalizedNoShowUserIds
        ));

        return $operation->fresh();
    }

    public function upsertSettlementDraft(User $actor, Operation $operation, array $data): OperationSettlement
    {
        $this->assertSettlementEditable($operation);

        return DB::transaction(function () use ($actor, $operation, $data) {
            $settlement = $this->firstOrNewSettlement($operation);
            $payload = $this->normalizeSettlementPayload($operation, $data, false);

            $settlement->forceFill([
                'money_rows' => $payload['money_rows'],
                'loot_rows' => $payload['loot_rows'],
            ])->save();

            return $settlement->fresh();
        });
    }

    public function finalizeSettlement(User $actor, Operation $operation, array $data = []): OperationSettlement
    {
        $this->assertSettlementEditable($operation);

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

    public function reopenSettlement(User $actor, Operation $operation): OperationSettlement
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
            $this->assertSettlementReopenSafe($settlement);
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

    protected function pullMediaId(array $data): array
    {
        if (! array_key_exists('media_id', $data)) {
            return [$data, null, false];
        }

        $mediaId = $data['media_id'];
        unset($data['media_id']);

        return [$data, $mediaId, true];
    }

    protected function ensureAfterActionDefaults(Operation $operation, string $status): Operation
    {
        if ($status !== OperationStatus::Completed->value) {
            return $operation;
        }

        $previousAttendanceUserIds = $this->normalizeUserIds($operation->after_action_attendance_user_ids ?? []);
        $previousNoShowUserIds = $this->normalizeUserIds($operation->after_action_no_show_user_ids ?? []);
        $attendanceUserIds = $this->defaultAttendanceUserIds($operation);
        $needsReport = blank($operation->after_action_report);
        $needsAttendance = empty($operation->after_action_attendance_user_ids);
        $needsNoShow = empty($operation->after_action_no_show_user_ids);

        if (! $needsReport && ! $needsAttendance && ! $needsNoShow) {
            $this->recalculateAfterActionStatsForUsers(array_merge(
                $previousAttendanceUserIds,
                $previousNoShowUserIds
            ));

            return $operation->fresh();
        }

        $operation->forceFill([
            'after_action_report' => $needsReport
                ? $this->defaultAfterActionReport($operation)
                : $operation->after_action_report,
            'after_action_attendance_user_ids' => $needsAttendance
                ? $attendanceUserIds
                : $this->normalizeUserIds($operation->after_action_attendance_user_ids ?? []),
            'after_action_no_show_user_ids' => $needsNoShow
                ? []
                : $this->normalizeUserIds($operation->after_action_no_show_user_ids ?? []),
            'after_action_report_updated_at' => now(),
        ])->save();

        $freshOperation = $operation->fresh();

        $this->recalculateAfterActionStatsForUsers(array_merge(
            $previousAttendanceUserIds,
            $previousNoShowUserIds,
            $this->normalizeUserIds($freshOperation->after_action_attendance_user_ids ?? []),
            $this->normalizeUserIds($freshOperation->after_action_no_show_user_ids ?? [])
        ));

        return $freshOperation;
    }

    protected function defaultAfterActionReport(Operation $operation): string
    {
        $outcome = match ($operation->completion_outcome) {
            CompletionOutcome::Success->value => 'Success',
            CompletionOutcome::Failed->value => 'Failure',
            default => 'Completed',
        };

        return implode("\n\n", [
            "Outcome: {$outcome}",
            'Summary:',
            'Objectives completed:',
            'Attendance notes:',
            'Lessons learned:',
            'Follow-up actions:',
        ]);
    }

    protected function defaultAttendanceUserIds(Operation $operation): array
    {
        $operation->loadMissing('participants:user_id,operation_id');

        return $operation->participants
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
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

    protected function recalculateAfterActionStatsForUsers(array $userIds): void
    {
        $userIds = $this->normalizeUserIds($userIds);

        if (empty($userIds)) {
            return;
        }

        $completedCounts = array_fill_keys($userIds, 0);
        $noShowCounts = array_fill_keys($userIds, 0);

        Operation::query()
            ->select('after_action_attendance_user_ids', 'after_action_no_show_user_ids')
            ->where('status', OperationStatus::Completed->value)
            ->where(function ($query) {
                $query->whereNotNull('after_action_attendance_user_ids')
                    ->orWhereNotNull('after_action_no_show_user_ids');
            })
            ->get()
            ->each(function (Operation $completedOperation) use (&$completedCounts, &$noShowCounts, $userIds) {
                $attendanceIds = $this->normalizeUserIds($completedOperation->after_action_attendance_user_ids ?? []);
                $noShowIds = $this->normalizeUserIds($completedOperation->after_action_no_show_user_ids ?? []);

                foreach ($userIds as $userId) {
                    if (in_array($userId, $attendanceIds, true)) {
                        $completedCounts[$userId]++;
                    }

                    if (in_array($userId, $noShowIds, true)) {
                        $noShowCounts[$userId]++;
                    }
                }
            });

        $users = \App\Models\User::query()
            ->whereIn('id', $userIds)
            ->get();

        foreach ($users as $user) {
            $user->forceFill([
                'operations_completed_count' => $completedCounts[$user->id] ?? 0,
                'operations_no_show_count' => $noShowCounts[$user->id] ?? 0,
            ])->save();
        }
    }

    protected function firstOrNewSettlement(Operation $operation): OperationSettlement
    {
        return $operation->relationLoaded('settlement')
            ? ($operation->getRelation('settlement') ?? new OperationSettlement(['operation_id' => $operation->id]))
            : OperationSettlement::query()->firstOrNew([
                'operation_id' => $operation->id,
            ]);
    }

    protected function assertSettlementEditable(Operation $operation): void
    {
        if (! $operation->isCompleted()) {
            throw ValidationException::withMessages([
                'operation' => 'Only completed operations can use settlements.',
            ]);
        }

        if ($this->settlementIsFinalized($operation)) {
            throw ValidationException::withMessages([
                'settlement' => 'This settlement is finalized. Reopen it before making changes.',
            ]);
        }
    }

    protected function settlementIsFinalized(Operation $operation): bool
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
    ): void
    {
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

    protected function assertSettlementReopenSafe(OperationSettlement $settlement): void
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
}
