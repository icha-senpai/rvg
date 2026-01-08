<?php

namespace App\Domain\Operations\Queries;

use App\Models\Operation;
use App\Models\User;

class OperationQuery
{
    public function forUser(User $user, int $perPage = 12)
    {
        $now = now();

        return Operation::visibleToUser($user)
            ->with(['squadron', 'creator'])
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
}
