<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\OperationRole;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OperationParticipantController extends Controller
{
    use AuthorizesRequests;

    public function join(Request $request, Operation $operation)
    {
        $this->authorize('view', $operation);

        $validated = $request->validate([
            'operation_role_id' => 'nullable|exists:operation_roles,id',
            'slot'              => 'nullable|string|max:255',
            'notes'             => 'nullable|string|max:500',
        ]);

        // Prevent duplicate join
        if ($operation->participants()
            ->where('user_id', auth()->id())
            ->exists()
        ) {
            return response()->json(['error' => 'Already joined this operation'], 422);
        }

        // Optional role validation
        $roleId = $validated['operation_role_id'] ?? null;
        if ($roleId) {
            $role = OperationRole::findOrFail($roleId);
            if ($role->operation_id !== $operation->id) {
                return response()->json(['error' => 'Invalid role for this operation'], 422);
            }

            if ($role->capacity !== null) {
                $filled = $role->participants()->count();
                if ($filled >= $role->capacity) {
                    return response()->json(['error' => 'Role is full'], 422);
                }
            }
        }

        $participant = $operation->participants()->create([
            'user_id'           => auth()->id(),
            'operation_role_id' => $roleId,
            'slot'              => $validated['slot'] ?? null,
            'attendance_status' => 'signed_up',
            'notes'             => $validated['notes'] ?? null,
            'stats'             => null,
        ]);

        return response()->json($participant->load('role', 'user'), 201);
    }

    public function leave(Operation $operation)
    {
        $user = auth()->user();

        $participant = $operation->participants()
            ->where('user_id', $user->id)
            ->first();

        if (!$participant) {
            return response()->json(['message' => 'Not in operation'], 404);
        }

        $participant->delete();

        return response()->json(['message' => 'Left operation']);
    }

    public function updateSlot(Request $request, Operation $operation, OperationParticipant $participant)
    {
        $this->authorize('manageMembers', $operation);

        $participant->update(
            $request->validate([
                'operation_role_id' => 'nullable|exists:operation_roles,id',
                'slot'              => 'nullable|string|max:255',
            ])
        );

        return response()->json($participant->fresh()->load('role', 'user'));
    }

    public function updateStats(Request $request, Operation $operation, OperationParticipant $participant)
    {
        $this->authorize('adjustStats', $operation);

        $participant->update([
            'stats' => $request->input('stats', []),
        ]);

        return response()->json($participant);
    }
}
