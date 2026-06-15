<?php

namespace App\Domain\Operations\Actions;

use App\Models\Operation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LeaveOperation
{
    public function execute(Operation $operation, User $user): string
    {
        $participant = $operation->participants()->where('user_id', $user->id)->first();

        if (! $participant) {
            throw ValidationException::withMessages([
                'participant' => 'You are not part of this operation.',
            ]);
        }

        if (
            $operation->rsvp_deadline
            && now()->greaterThanOrEqualTo($operation->rsvp_deadline)
            && $operation->starts_at
            && now()->lt($operation->starts_at)
        ) {
            $user->increment('operations_left_early_count');

            $participant->forceFill([
                'attendance_status' => 'signed_up',
                'runtime_status' => \App\Models\OperationParticipant::RUNTIME_STATUS_SIGNED_OFF_BEFORE_START,
                'runtime_source' => $participant->runtime_source ?: \App\Models\OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
                'signed_off_at' => now(),
                'synced_in_at' => null,
            ])->save();

            return 'signed_off_before_start';
        }

        $participant->delete();

        return 'left';
    }
}
