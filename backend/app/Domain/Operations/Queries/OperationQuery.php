<?php

namespace App\Domain\Operations\Queries;

use App\Models\Operation;
use App\Models\User;

class OperationQuery
{
    public function forUser(User $user)
    {
        return Operation::visibleToUser($user)
            ->orderByDesc('starts_at')
            ->get();
    }

    public function forSquadron(int $squadronId)
    {
        return Operation::where('squadron_id', $squadronId)
            ->orderByDesc('starts_at')
            ->get();
    }
}
