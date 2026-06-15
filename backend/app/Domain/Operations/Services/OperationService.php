<?php

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Actions\CancelOperation;
use App\Domain\Operations\Actions\CreateOperation;
use App\Domain\Operations\Actions\TransitionOperation;
use App\Domain\Operations\Actions\UpdateOperation;
use App\Models\Operation;
use App\Models\OperationSettlement;
use App\Models\Squadron;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Coordinates operation actions while keeping public entrypoints stable and
 * delegating read/write subdomains to focused collaborators.
 */
class OperationService
{
    public function __construct(
        protected OperationMediaService $operationMedia,
        protected OperationUpsertService $upserts,
        protected OperationAfterActionService $afterAction,
        protected OperationSettlementService $settlements,
    ) {}

    public function create(array $data, ?Squadron $squadron = null): Operation
    {
        $prepared = $this->upserts->prepare($data);

        $operation = (new CreateOperation)->execute($prepared['data'], $squadron);
        $this->upserts->syncRoles($operation, $prepared['data']);

        if ($prepared['should_sync_media']) {
            $this->operationMedia->syncOperationImage($operation, $prepared['media_id']);
        }

        return $operation;
    }

    public function update(Operation $operation, array $data): Operation
    {
        $prepared = $this->upserts->prepare($data);

        $operation = (new UpdateOperation)->execute($operation, $prepared['data']);
        $this->upserts->syncRoles($operation, $prepared['data']);

        if ($prepared['should_sync_media']) {
            $this->operationMedia->syncOperationImage($operation, $prepared['media_id']);
        }

        return $operation;
    }

    public function cancel(Operation $operation, ?string $reason = null): Operation
    {
        return (new CancelOperation)->execute($operation, $reason);
    }

    public function transition(Operation $operation, string $status, ?string $reason = null, ?string $outcome = null): Operation
    {
        try {
            $updated = (new TransitionOperation)->execute($operation, $status, $reason, $outcome);
            $updated = $this->afterAction->ensureDefaults($updated, $status);

            if ($status === \App\Domain\Operations\Enums\OperationStatus::Completed->value) {
                $updated = $this->settlements->seedDraftFromPrep($updated);
            }

            return $updated;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'status' => $e->getMessage(),
            ]);
        }
    }

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

    public function updateAfterActionReport(
        Operation $operation,
        ?string $report,
        array $attendanceUserIds = [],
        array $noShowUserIds = [],
        array $signedOffEarlyUserIds = [],
        array $excusedUserIds = []
    ): Operation
    {
        return $this->afterAction->updateReport(
            $operation,
            $report,
            $attendanceUserIds,
            $noShowUserIds,
            $signedOffEarlyUserIds,
            $excusedUserIds
        );
    }

    public function upsertSettlementDraft(User $actor, Operation $operation, array $data): OperationSettlement
    {
        return $this->settlements->upsertDraft($actor, $operation, $data);
    }

    public function upsertRuntimeFundsPrep(User $actor, Operation $operation, array $data): OperationSettlement
    {
        return $this->settlements->upsertPrepDraft($actor, $operation, $data);
    }

    public function finalizeSettlement(User $actor, Operation $operation, array $data = []): OperationSettlement
    {
        return $this->settlements->finalize($actor, $operation, $data);
    }

    public function reopenSettlement(User $actor, Operation $operation): OperationSettlement
    {
        return $this->settlements->reopen($actor, $operation);
    }
}
