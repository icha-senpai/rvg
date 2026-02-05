<?php

namespace App\Domain\Operations\Actions;

use App\Models\Operation;
use App\Models\User;
use App\Models\OperationParticipant;
use Illuminate\Validation\ValidationException;

class JoinOperation
{
    public function execute(Operation $operation, User $user, array $data): OperationParticipant
    {
        // Duplicate prevention
        if ($operation->participants()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'participant' => 'Already joined this operation.',
            ]);
        }

        $participant = $operation->participants()->create([
            'user_id'           => $user->id,
            'operation_role_id' => $data['operation_role_id'] ?? null,
            'slot'              => $data['slot'] ?? null,
            'attendance_status' => 'signed_up',
            'notes'             => $data['notes'] ?? null,
            'stats'             => null,
        ]);

        $user->increment('operations_joined_count');

        return $participant;
    }
}
