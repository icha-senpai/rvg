<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Operation;
use App\Models\OperationParticipant;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Domain\Operations\Services\ParticipantService;
use Illuminate\Validation\ValidationException;

/**
 * JSON API controller for operation participation actions and participant
 * updates.
 */
class OperationParticipantController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ParticipantService $participants
    ) {}

    /**
     * Join an operation with an optional requested slot or role.
     */
    public function join(Request $request, Operation $operation)
    {
        $this->authorize('view', $operation);

        $validated = $request->validate([
            'operation_role_id' => 'nullable|exists:operation_roles,id',
            'slot'              => 'nullable|string|max:255',
            'notes'             => 'nullable|string|max:500',
        ]);

        try {
            $participant = $this->participants->join($operation, $request->user(), $validated);
        } catch (ValidationException $e) {
            $message = $this->firstValidationMessage($e, 'Already joined this operation');

            return response()->json([
                'status' => 'error',
                'message' => $message,
                'error' => $message,
            ], 422);
        }

        return response()->json($participant->load('role', 'user'), 201);
    }

    /**
     * Leave an operation as the authenticated participant.
     */
    public function leave(Request $request, Operation $operation)
    {
        try {
            $this->participants->leave($operation, $request->user());
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not in operation',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Left operation',
        ]);
    }

    /**
     * Update a participant's assigned slot or operation role.
     *
     * Participants may edit their own slot choice, while changes to another
     * participant require the operation member-management permission.
     */
    public function updateSlot(Request $request, Operation $operation, OperationParticipant $participant)
    {
        if ($participant->operation_id !== $operation->id) {
            abort(404);
        }

        if ($participant->user_id !== $request->user()->id) {
            $this->authorize('manageMembers', $operation);
        }

        try {
            $participant = $this->participants->updateSlot(
                $operation,
                $participant,
                $request->validate([
                    'operation_role_id' => 'nullable|exists:operation_roles,id',
                    'slot'              => 'nullable|string|max:255',
                ])
            );
        } catch (ValidationException $e) {
            $message = $this->firstValidationMessage($e, 'Unable to update role');

            return response()->json([
                'status' => 'error',
                'message' => $message,
                'error' => $message,
            ], 422);
        }

        return response()->json($participant->load('role', 'user'));
    }

    /**
     * Update the stored stats payload for one participant.
     */
    public function updateStats(Request $request, Operation $operation, OperationParticipant $participant)
    {
        $this->authorize('adjustStats', $operation);

        $participant = $this->participants->updateStats(
            $participant,
            $request->input('stats', [])
        );

        return response()->json($participant);
    }

    /**
     * Extract the first validation error message for API-friendly error payloads.
     */
    protected function firstValidationMessage(ValidationException $e, string $fallback): string
    {
        return collect($e->errors())
            ->flatten()
            ->first() ?? $fallback;
    }
}
