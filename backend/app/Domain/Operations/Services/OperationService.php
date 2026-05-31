<?php

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Actions\CancelOperation;
use App\Domain\Operations\Actions\CreateOperation;
use App\Domain\Operations\Actions\TransitionOperation;
use App\Domain\Operations\Actions\UpdateOperation;
use App\Domain\Operations\Enums\CompletionOutcome;
use App\Domain\Operations\Enums\OperationStatus;
use App\Models\Operation;
use App\Models\Squadron;
use Illuminate\Validation\ValidationException;

/**
 * Coordinates operation actions while keeping shared normalization and error
 * handling rules in one domain service.
 */
class OperationService
{
    public function __construct(
        protected OperationMediaService $operationMedia
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

        return $data;
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
}
