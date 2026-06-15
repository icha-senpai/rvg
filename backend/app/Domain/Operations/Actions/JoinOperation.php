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
        $existingParticipant = $operation->participants()->where('user_id', $user->id)->first();

        if ($existingParticipant && ! $existingParticipant->isSignedOffBeforeStart()) {
            throw ValidationException::withMessages([
                'participant' => 'Already joined this operation.',
            ]);
        }

        if ($existingParticipant) {
            $existingParticipant->forceFill([
                'operation_role_id' => $data['operation_role_id'] ?? null,
                'slot' => $data['slot'] ?? null,
                'attendance_status' => 'signed_up',
                'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
                'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
                'signed_off_at' => null,
                'synced_in_at' => null,
                'notes' => $data['notes'] ?? null,
                'stats' => null,
            ])->save();

            $user->increment('operations_joined_count');

            return $existingParticipant->fresh();
        }

        $participant = $operation->participants()->create([
            'user_id'           => $user->id,
            'operation_role_id' => $data['operation_role_id'] ?? null,
            'slot'              => $data['slot'] ?? null,
            'attendance_status' => 'signed_up',
            'runtime_status'    => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source'    => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'notes'             => $data['notes'] ?? null,
            'stats'             => null,
        ]);

        $user->increment('operations_joined_count');

        return $participant;
    }
}
