<?php

namespace App\Application\Operations\Queries;

use App\Domain\Operations\Enums\OperationStatus;
use App\Models\Operation;
use App\Models\User;

/**
 * Public query entry point for operation list screens and API consumers.
 */
class OperationQuery
{
    public function __construct(
        protected OperationSummaryListQuery $summary,
    ) {}

    public function forUser(User $user, int $perPage = 12)
    {
        $now = now();

        return Operation::visibleToUser($user)
            ->withCount('participants')
            ->withCount([
                'participants as joined_by_me' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->with(['squadron', 'creator.roles'])
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 2 WHEN starts_at >= ? THEN 0 ELSE 1 END', [$now])
            ->orderByRaw('CASE WHEN starts_at >= ? THEN starts_at END ASC', [$now])
            ->orderByRaw('CASE WHEN starts_at < ? THEN starts_at END DESC', [$now])
            ->paginate($perPage);
    }

    public function forSquadron(int $squadronId)
    {
        return Operation::where('squadron_id', $squadronId)
            ->orderBy('starts_at', 'asc')
            ->get();
    }

    public function summaryList(string $status = 'active', string $search = '', int $perPage = 12)
    {
        return $this->summary->paginate($status, $search, $perPage)[0];
    }

    public function dashboardList(string $status = 'active', string $search = '', int $perPage = 12): array
    {
        return $this->summary->paginate($status, $search, $perPage);
    }

    public function visibleStatuses(): array
    {
        return [
            OperationStatus::Published->value,
            OperationStatus::InProgress->value,
        ];
    }
}
