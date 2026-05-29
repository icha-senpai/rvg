<?php

namespace App\Application\Operations\Queries;

use App\Application\Operations\Presenters\OperationPresenter;
use App\Domain\Operations\Enums\OperationStatus;
use App\Models\Operation;

/**
 * Builds the summarized operation list used by the dashboard and API index.
 */
class OperationSummaryListQuery
{
    public function paginate(string $status = 'active', string $search = '', int $perPage = 12)
    {
        [$status, $search] = $this->normalizeFilters($status, $search);
        $now = now();

        $operations = $this->buildQuery($status, $search)
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 2 WHEN starts_at >= ? THEN 0 ELSE 1 END', [$now])
            ->orderByRaw('CASE WHEN starts_at >= ? THEN starts_at END ASC', [$now])
            ->orderByRaw('CASE WHEN starts_at < ? THEN starts_at END DESC', [$now])
            ->paginate($perPage)
            ->withQueryString();

        $operations->setCollection(
            $operations->getCollection()->map(
                fn (Operation $operation) => OperationPresenter::make($operation)->summary()
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

    protected function normalizeFilters(string $status, string $search): array
    {
        $status = (string) $status;
        $search = trim((string) $search);

        $allowedStatuses = array_merge(['active', 'all'], OperationStatus::values());

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'active';
        }

        return [$status, $search];
    }

    protected function buildQuery(string $status, string $search)
    {
        $query = Operation::query()
            ->with(['squadron.leader', 'creator.roles']);

        if ($status === 'active') {
            $query->whereIn('status', [
                OperationStatus::Published->value,
                OperationStatus::InProgress->value,
            ]);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $searchId = null;
            if (preg_match('/^#?(\d+)$/', $search, $matches)) {
                $searchId = (int) $matches[1];
            }

            $query->where(function ($nestedQuery) use ($search, $searchId) {
                $nestedQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");

                $nestedQuery->orWhereHas('squadron', function ($squadronQuery) use ($search) {
                    $squadronQuery->where('name', 'like', "%{$search}%");
                });

                $nestedQuery->orWhereHas('creator', function ($creatorQuery) use ($search) {
                    $creatorQuery->where('rsi_handle', 'like', "%{$search}%");
                });

                if ($searchId !== null) {
                    $nestedQuery->orWhere('id', $searchId);
                }
            });
        }

        return $query;
    }
}
