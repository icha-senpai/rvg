<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\Operations\Services\ParticipantService;
use App\Models\Operation;
use App\Models\OperationParticipant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Handles the web participation actions for joining, leaving, and adjusting a
 * participant's selected slot.
 */
class OperationParticipantController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ParticipantService $participants
    ) {}

    /**
     * Let the authenticated user join the operation from the web UI.
     */
    public function join(Request $request, Operation $operation)
    {
        $this->authorize('view', $operation);

        $data = $request->validate([
            'slot'              => 'nullable|string|max:255',
            'notes'             => 'nullable|string|max:500',
            'operation_role_id' => 'nullable|exists:operation_roles,id',
        ]);

        // Empty slot inputs are normalized to null so the participant service
        // does not need to treat empty strings as a special case.
        if (array_key_exists('slot', $data) && $data['slot'] === '') {
            $data['slot'] = null;
        }

        $this->participants->join($operation, $request->user(), $data);

        return back()
            ->with('success', 'Joined operation.');
    }

    /**
     * Let the authenticated user leave the operation from the web UI.
     */
    public function leave(Request $request, Operation $operation)
    {
        $this->authorize('view', $operation);

        $this->participants->leave($operation, $request->user());

        return back()
            ->with('success', 'Left operation.');
    }

    /**
     * Update the participant's selected slot for the given operation.
     */
    public function updateSlot(
        Request $request,
        Operation $operation,
        OperationParticipant $participant
    ) {
        if ($participant->operation_id !== $operation->id) {
            abort(404);
        }

        $canAssignSlots = $request->user()->can('assignSlots', $operation);
        $isOwnParticipant = (int) $participant->user_id === (int) $request->user()->id;

        if (! $canAssignSlots && ! $isOwnParticipant) {
            abort(403);
        }

        $data = $request->validate([
            'slot'              => 'nullable|string|max:255',
            'operation_role_id' => 'nullable|exists:operation_roles,id',
        ]);

        // Empty slot inputs are normalized to null so the participant service
        // can treat "cleared" and "unset" consistently.
        if (array_key_exists('slot', $data) && $data['slot'] === '') {
            $data['slot'] = null;
        }

        $this->participants->updateSlot($operation, $participant, $data);

        return back()
            ->with('success', 'Role updated.');
    }
}
