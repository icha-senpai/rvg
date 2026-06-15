<?php

namespace App\Application\Operations;

use App\Models\Operation;
use Illuminate\Support\Collection;

class OperationParticipantPayloadService
{
    public function participants(Operation $operation, bool $canViewSlots): Collection
    {
        return $operation->participants
            ->map(fn ($participant) => $this->participant($participant, $canViewSlots))
            ->values();
    }

    public function participant($participant, bool $includeAssignments): array
    {
        return [
            'id' => $participant->id,
            'slot' => $includeAssignments ? $participant->slot : null,
            'role' => $includeAssignments && $participant->role ? [
                'id' => $participant->role->id,
                'role_name' => $participant->role->role_name,
                'role_display_name' => $participant->role->role_display_name,
                'capacity' => $participant->role->capacity,
            ] : null,
            'attendance_status' => $includeAssignments ? $participant->attendance_status : null,
            'runtime_status' => $participant->runtime_status,
            'runtime_source' => $participant->runtime_source,
            'signed_off_at' => $participant->signed_off_at?->toIso8601String(),
            'synced_in_at' => $participant->synced_in_at?->toIso8601String(),
            'starting_auec' => $participant->starting_auec,
            'ending_auec' => $participant->ending_auec,
            'runtime_notes' => $participant->runtime_notes,
            'notes' => $includeAssignments ? $participant->notes : null,
            'user' => [
                'id' => $participant->user?->id,
                'rsi_handle' => $participant->user?->rsi_handle,
                'display_name' => $participant->user?->display_name,
                'name' => $participant->user?->name,
                'discord_avatar' => $participant->user?->discord_avatar,
                'avatar' => $participant->user?->avatar,
            ],
        ];
    }
}
