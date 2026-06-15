<?php

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Enums\CompletionOutcome;
use App\Domain\Operations\Enums\OperationStatus;
use App\Models\Operation;
use Illuminate\Validation\ValidationException;

class OperationAfterActionService
{
    public function updateReport(
        Operation $operation,
        ?string $report,
        array $attendanceUserIds = [],
        array $noShowUserIds = [],
        array $signedOffEarlyUserIds = [],
        array $excusedUserIds = []
    ): Operation
    {
        if (! $operation->isCompleted()) {
            throw ValidationException::withMessages([
                'after_action_report' => 'After Action Reports are only available for completed operations.',
            ]);
        }

        $previousAttendanceUserIds = $this->normalizeUserIds($operation->after_action_attendance_user_ids ?? []);
        $previousNoShowUserIds = $this->normalizeUserIds($operation->after_action_no_show_user_ids ?? []);
        $previousSignedOffEarlyUserIds = $this->normalizeUserIds($operation->after_action_signed_off_early_user_ids ?? []);
        $previousExcusedUserIds = $this->normalizeUserIds($operation->after_action_excused_user_ids ?? []);

        $normalizedAttendanceUserIds = $this->normalizeUserIds($attendanceUserIds);
        $normalizedNoShowUserIds = collect($this->normalizeUserIds($noShowUserIds))
            ->reject(fn ($id) => in_array($id, $normalizedAttendanceUserIds, true))
            ->values()
            ->all();
        $normalizedSignedOffEarlyUserIds = collect($this->normalizeUserIds($signedOffEarlyUserIds))
            ->reject(fn ($id) => in_array($id, $normalizedAttendanceUserIds, true))
            ->reject(fn ($id) => in_array($id, $normalizedNoShowUserIds, true))
            ->values()
            ->all();
        $normalizedExcusedUserIds = collect($this->normalizeUserIds($excusedUserIds))
            ->reject(fn ($id) => in_array($id, $normalizedAttendanceUserIds, true))
            ->reject(fn ($id) => in_array($id, $normalizedNoShowUserIds, true))
            ->reject(fn ($id) => in_array($id, $normalizedSignedOffEarlyUserIds, true))
            ->values()
            ->all();

        if (
            $this->settlementIsFinalized($operation)
            && (
                $normalizedAttendanceUserIds !== $previousAttendanceUserIds
                || $normalizedNoShowUserIds !== $previousNoShowUserIds
                || $normalizedSignedOffEarlyUserIds !== $previousSignedOffEarlyUserIds
                || $normalizedExcusedUserIds !== $previousExcusedUserIds
            )
        ) {
            throw ValidationException::withMessages([
                'attendance_user_ids' => 'Final attendance is locked once the operation settlement is finalized. Reopen the settlement to change attendance, no-shows, signed-off-early members, or excused members.',
            ]);
        }

        $operation->forceFill([
            'after_action_report' => filled($report) ? trim((string) $report) : null,
            'after_action_attendance_user_ids' => $normalizedAttendanceUserIds,
            'after_action_no_show_user_ids' => $normalizedNoShowUserIds,
            'after_action_signed_off_early_user_ids' => $normalizedSignedOffEarlyUserIds,
            'after_action_excused_user_ids' => $normalizedExcusedUserIds,
            'after_action_report_updated_at' => now(),
        ])->save();

        $this->recalculateAfterActionStatsForUsers(array_merge(
            $previousAttendanceUserIds,
            $previousNoShowUserIds,
            $previousSignedOffEarlyUserIds,
            $previousExcusedUserIds,
            $normalizedAttendanceUserIds,
            $normalizedNoShowUserIds,
            $normalizedSignedOffEarlyUserIds,
            $normalizedExcusedUserIds
        ));

        return $operation->fresh();
    }

    public function ensureDefaults(Operation $operation, string $status): Operation
    {
        if ($status !== OperationStatus::Completed->value) {
            return $operation;
        }

        $previousAttendanceUserIds = $this->normalizeUserIds($operation->after_action_attendance_user_ids ?? []);
        $previousNoShowUserIds = $this->normalizeUserIds($operation->after_action_no_show_user_ids ?? []);
        $previousSignedOffEarlyUserIds = $this->normalizeUserIds($operation->after_action_signed_off_early_user_ids ?? []);
        $previousExcusedUserIds = $this->normalizeUserIds($operation->after_action_excused_user_ids ?? []);
        $attendanceUserIds = $this->defaultAttendanceUserIds($operation);
        $noShowUserIds = $this->defaultNoShowUserIds($operation);
        $signedOffEarlyUserIds = $this->defaultSignedOffEarlyUserIds($operation);
        $excusedUserIds = $this->defaultExcusedUserIds($operation);
        $needsReport = blank($operation->after_action_report);
        $needsAttendance = empty($operation->after_action_attendance_user_ids);
        $needsNoShow = empty($operation->after_action_no_show_user_ids);
        $needsSignedOffEarly = empty($operation->after_action_signed_off_early_user_ids);
        $needsExcused = empty($operation->after_action_excused_user_ids);

        if (! $needsReport && ! $needsAttendance && ! $needsNoShow && ! $needsSignedOffEarly && ! $needsExcused) {
            $this->recalculateAfterActionStatsForUsers(array_merge(
                $previousAttendanceUserIds,
                $previousNoShowUserIds,
                $previousSignedOffEarlyUserIds,
                $previousExcusedUserIds
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
                ? $noShowUserIds
                : $this->normalizeUserIds($operation->after_action_no_show_user_ids ?? []),
            'after_action_signed_off_early_user_ids' => $needsSignedOffEarly
                ? $signedOffEarlyUserIds
                : $this->normalizeUserIds($operation->after_action_signed_off_early_user_ids ?? []),
            'after_action_excused_user_ids' => $needsExcused
                ? $excusedUserIds
                : $this->normalizeUserIds($operation->after_action_excused_user_ids ?? []),
            'after_action_report_updated_at' => now(),
        ])->save();

        $freshOperation = $operation->fresh();

        $this->recalculateAfterActionStatsForUsers(array_merge(
            $previousAttendanceUserIds,
            $previousNoShowUserIds,
            $previousSignedOffEarlyUserIds,
            $previousExcusedUserIds,
            $this->normalizeUserIds($freshOperation->after_action_attendance_user_ids ?? []),
            $this->normalizeUserIds($freshOperation->after_action_no_show_user_ids ?? []),
            $this->normalizeUserIds($freshOperation->after_action_signed_off_early_user_ids ?? []),
            $this->normalizeUserIds($freshOperation->after_action_excused_user_ids ?? [])
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
        $operation->loadMissing('participants:user_id,operation_id,runtime_status,signed_off_at,synced_in_at');

        $participants = $operation->participants
            ->filter(fn ($participant) => filled($participant->user_id))
            ->values();

        $hasRuntimeSignals = $participants->contains(function ($participant) {
            return filled($participant->runtime_status)
                || $participant->signed_off_at !== null
                || $participant->synced_in_at !== null;
        });

        if ($hasRuntimeSignals) {
            $syncedAttendanceUserIds = $participants
                ->filter(function ($participant) {
                    return $participant->synced_in_at !== null
                        && ! $participant->isSignedOffBeforeStart()
                        && $participant->runtime_status !== \App\Models\OperationParticipant::RUNTIME_STATUS_NO_SHOW
                        && $participant->runtime_status !== \App\Models\OperationParticipant::RUNTIME_STATUS_EXCUSED
                        && $participant->runtime_status !== \App\Models\OperationParticipant::RUNTIME_STATUS_TECHNICAL_ISSUE;
                })
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if ($syncedAttendanceUserIds !== []) {
                return $syncedAttendanceUserIds;
            }

            return $participants
                ->reject(function ($participant) {
                    return $participant->isSignedOffBeforeStart()
                        || $participant->runtime_status === \App\Models\OperationParticipant::RUNTIME_STATUS_NO_SHOW
                        || $participant->runtime_status === \App\Models\OperationParticipant::RUNTIME_STATUS_EXCUSED
                        || $participant->runtime_status === \App\Models\OperationParticipant::RUNTIME_STATUS_TECHNICAL_ISSUE;
                })
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return $participants
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function defaultNoShowUserIds(Operation $operation): array
    {
        $operation->loadMissing('participants:user_id,operation_id,runtime_status,signed_off_at,synced_in_at');

        $participants = $operation->participants
            ->filter(fn ($participant) => filled($participant->user_id))
            ->values();

        $hasRuntimeSignals = $participants->contains(function ($participant) {
            return filled($participant->runtime_status)
                || $participant->signed_off_at !== null
                || $participant->synced_in_at !== null;
        });

        if (! $hasRuntimeSignals) {
            return [];
        }

        return $participants
            ->filter(fn ($participant) => $participant->runtime_status === \App\Models\OperationParticipant::RUNTIME_STATUS_NO_SHOW)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function defaultSignedOffEarlyUserIds(Operation $operation): array
    {
        $operation->loadMissing('participants:user_id,operation_id,runtime_status,signed_off_at,synced_in_at');

        return $operation->participants
            ->filter(fn ($participant) => filled($participant->user_id))
            ->filter(fn ($participant) => $participant->isSignedOffBeforeStart())
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function defaultExcusedUserIds(Operation $operation): array
    {
        $operation->loadMissing('participants:user_id,operation_id,runtime_status,signed_off_at,synced_in_at');

        return $operation->participants
            ->filter(fn ($participant) => filled($participant->user_id))
            ->filter(fn ($participant) => in_array($participant->runtime_status, [
                \App\Models\OperationParticipant::RUNTIME_STATUS_EXCUSED,
                \App\Models\OperationParticipant::RUNTIME_STATUS_TECHNICAL_ISSUE,
            ], true))
            ->pluck('user_id')
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
        $excusedCounts = array_fill_keys($userIds, 0);

        Operation::query()
            ->select('after_action_attendance_user_ids', 'after_action_no_show_user_ids', 'after_action_signed_off_early_user_ids', 'after_action_excused_user_ids')
            ->where('status', OperationStatus::Completed->value)
            ->where(function ($query) {
                $query->whereNotNull('after_action_attendance_user_ids')
                    ->orWhereNotNull('after_action_no_show_user_ids')
                    ->orWhereNotNull('after_action_signed_off_early_user_ids')
                    ->orWhereNotNull('after_action_excused_user_ids');
            })
            ->get()
            ->each(function (Operation $completedOperation) use (&$completedCounts, &$noShowCounts, &$excusedCounts, $userIds) {
                $attendanceIds = $this->normalizeUserIds($completedOperation->after_action_attendance_user_ids ?? []);
                $noShowIds = $this->normalizeUserIds($completedOperation->after_action_no_show_user_ids ?? []);
                $excusedIds = $this->normalizeUserIds($completedOperation->after_action_excused_user_ids ?? []);

                foreach ($userIds as $userId) {
                    if (in_array($userId, $attendanceIds, true)) {
                        $completedCounts[$userId]++;
                    }

                    if (in_array($userId, $noShowIds, true)) {
                        $noShowCounts[$userId]++;
                    }

                    if (in_array($userId, $excusedIds, true)) {
                        $excusedCounts[$userId]++;
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
                'operations_excused_count' => $excusedCounts[$user->id] ?? 0,
            ])->save();
        }
    }

    protected function settlementIsFinalized(Operation $operation): bool
    {
        $settlement = $operation->relationLoaded('settlement')
            ? $operation->getRelation('settlement')
            : $operation->settlement()->first();

        return (bool) $settlement?->finalized_at;
    }
}
