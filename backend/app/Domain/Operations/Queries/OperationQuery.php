<?php

namespace App\Domain\Operations\Queries;

use App\Models\Operation;
use App\Models\User;

class OperationQuery
{
    public function forUser(User $user, int $perPage = 12)
    {
        return Operation::visibleToUser($user)
            ->with(['squadron', 'creator'])
            ->orderBy('starts_at', 'asc')
            ->paginate($perPage);
    }

    public function forSquadron(int $squadronId)
    {
        return Operation::where('squadron_id', $squadronId)
            ->orderBy('starts_at', 'asc')
            ->get();
    }
}
