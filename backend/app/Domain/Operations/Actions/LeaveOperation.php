<?php

namespace App\Domain\Operations\Actions;

use App\Models\Operation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LeaveOperation
{
    public function execute(Operation $operation, User $user): void
    {
        $participant = $operation->participants()->where('user_id', $user->id)->first();

        if (! $participant) {
            throw ValidationException::withMessages([
                'participant' => 'You are not part of this operation.',
            ]);
        }

        $participant->delete();
    }
}
