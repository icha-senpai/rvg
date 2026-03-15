<?php

namespace App\Domain\Operations\Queries;

use App\Models\Operation;
use App\Models\User;

/**
 * Public query entry point for operation list screens and API consumers.
 *
 * This facade keeps a stable API for callers while the more specialized summary
 * list behavior lives in a dedicated query collaborator.
 */
class OperationQuery
{
    public function __construct(
        protected OperationSummaryListQuery $summary,
    ) {}

    /**
     * Return the operation list visible to a specific user, including helper
     * counts used by the member-facing operations page.
     */
    public function forUser(User $user, int $perPage = 12)
    {
        $now = now();

        return Operation::visibleToUser($user)
            ->withCount('participants')
            ->withCount([
                'participants as joined_by_me' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->with(['squadron', 'creator.roles'])
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 2 WHEN starts_at >= ? THEN 0 ELSE 1 END', [$now])
            ->orderByRaw('CASE WHEN starts_at >= ? THEN starts_at END ASC', [$now])
            ->orderByRaw('CASE WHEN starts_at < ? THEN starts_at END DESC', [$now])
            ->paginate($perPage);
    }

    /**
     * Return all operations that belong to one squadron in chronological order.
     */
    public function forSquadron(int $squadronId)
    {
        return Operation::where('squadron_id', $squadronId)
            ->orderBy('starts_at', 'asc')
            ->get();
    }

    /**
     * Return the summarized operation list payload used by API endpoints.
     */
    public function summaryList(string $status = 'active', string $search = '', int $perPage = 12)
    {
        return $this->summary->paginate($status, $search, $perPage)[0];
    }

    /**
     * Return the dashboard-ready operation list together with the normalized
     * filter state that the frontend should preserve in the UI.
     */
    public function dashboardList(string $status = 'active', string $search = '', int $perPage = 12): array
    {
        return $this->summary->paginate($status, $search, $perPage);
    }
}
