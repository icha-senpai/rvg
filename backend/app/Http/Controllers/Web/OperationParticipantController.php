<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\Operations\Services\ParticipantService;
use App\Models\Operation;
use App\Models\OperationParticipant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OperationParticipantController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ParticipantService $participants
    ) {}

    public function join(Request $request, Operation $operation)
    {
        $this->authorize('view', $operation);

        $data = $request->validate([
            'slot'              => 'nullable|string|max:255',
            'notes'             => 'nullable|string|max:500',
            'operation_role_id' => 'nullable|exists:operation_roles,id',
        ]);

        if (array_key_exists('slot', $data) && $data['slot'] === '') {
            $data['slot'] = null;
        }

        $this->participants->join($operation, $request->user(), $data);

        return back()
            ->with('success', 'Joined operation.');
    }

    public function leave(Request $request, Operation $operation)
    {
        $this->authorize('view', $operation);

        $this->participants->leave($operation, $request->user());

        return back()
            ->with('success', 'Left operation.');
    }

    public function updateSlot(
        Request $request,
        Operation $operation,
        OperationParticipant $participant
    ) {
        if ($participant->operation_id !== $operation->id) {
            abort(404);
        }

        if ($participant->user_id !== $request->user()->id) {
            $this->authorize('manageMembers', $operation);
        }

        $data = $request->validate([
            'slot'              => 'nullable|string|max:255',
            'operation_role_id' => 'nullable|exists:operation_roles,id',
        ]);

        if (array_key_exists('slot', $data) && $data['slot'] === '') {
            $data['slot'] = null;
        }

        $this->participants->updateSlot($operation, $participant, $data);

        return back()
            ->with('success', 'Role updated.');
    }
}
