<?php

namespace App\Domain\Operations\Queries;

use App\Domain\Operations\Presenters\OperationPresenter;
use App\Models\Operation;

/**
 * Builds the summarized operation list used by the dashboard and API index.
 *
 * This class owns filter normalization, search behavior, ordering rules, and
 * summary presentation so those concerns stay out of controllers.
 */
class OperationSummaryListQuery
{
    /**
     * Paginate the summarized operation list and return both the paginator and
     * the normalized filters that produced it.
     */
    public function paginate(string $status = 'active', string $search = '', int $perPage = 12)
    {
        [$status, $search] = $this->normalizeFilters($status, $search);
        $now = now();

        // Upcoming operations are shown first, then past operations, with null
        // start times pushed to the bottom so dashboard results stay predictable.
        $operations = $this->buildQuery($status, $search)
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

    /**
     * Normalize dashboard filters into a safe, expected set of values.
     */
    protected function normalizeFilters(string $status, string $search): array
    {
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

        return [$status, $search];
    }

    /**
     * Build the shared query used by both the API summary list and the web
     * dashboard list.
     */
    protected function buildQuery(string $status, string $search)
    {
        $query = Operation::query()
            ->with(['squadron.leader', 'creator.roles']);

        // The synthetic "active" filter intentionally groups published and
        // in-progress operations because the dashboard treats both as live work.
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

            // Search matches title/description text, related squadron names,
            // creator RSI handles, and a plain numeric operation id shortcut.
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

        return $query;
    }
}
