<?php

namespace App\Domain\Operations\Queries;

use App\Domain\Operations\Presenters\OperationPresenter;
use App\Models\Operation;
use App\Models\User;

class OperationQuery
{
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

    public function forSquadron(int $squadronId)
    {
        return Operation::where('squadron_id', $squadronId)
            ->orderBy('starts_at', 'asc')
            ->get();
    }

    public function dashboardList(string $status = 'active', string $search = '', int $perPage = 12): array
    {
        $now = now();

        $status = (string) $status;
        $search = trim((string) $search);

        $allowedStatuses = [
            'active',
            'all',
            'draft',
            'published',
            'in_progress',
            'completed',
            'canceled',
        ];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'active';
        }

        $query = Operation::query()
            ->with(['squadron.leader', 'creator.roles']);

        if ($status === 'active') {
            $query->whereIn('status', ['published', 'in_progress']);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $searchId = null;
            if (preg_match('/^#?(\d+)$/', $search, $matches)) {
                $searchId = (int) $matches[1];
            }

            $query->where(function ($q) use ($search, $searchId) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");

                $q->orWhereHas('squadron', function ($squadronQuery) use ($search) {
                    $squadronQuery->where('name', 'like', "%{$search}%");
                });

                $q->orWhereHas('creator', function ($creatorQuery) use ($search) {
                    $creatorQuery->where('rsi_handle', 'like', "%{$search}%");
                });

                if ($searchId !== null) {
                    $q->orWhere('id', $searchId);
                }
            });
        }

        $operations = $query
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 2 WHEN starts_at >= ? THEN 0 ELSE 1 END', [$now])
            ->orderByRaw('CASE WHEN starts_at >= ? THEN starts_at END ASC', [$now])
            ->orderByRaw('CASE WHEN starts_at < ? THEN starts_at END DESC', [$now])
            ->paginate($perPage)
            ->withQueryString();

        $operations->setCollection(
            $operations->getCollection()->map(
                fn (Operation $op) => OperationPresenter::make($op)->summary()
            )
        );

        return [
            $operations,
            [
                'status' => $status,
                'search' => $search,
            ],
        ];
    }
}
