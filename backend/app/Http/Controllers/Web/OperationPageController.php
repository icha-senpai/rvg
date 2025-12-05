<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use App\Models\Operation;
use App\Models\Squadron;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OperationPageController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $operations = Operation::query()
            ->with('squadron:id,name')
            ->orderByDesc('starts_at')
            ->get();

        return Inertia::render('Operations/MissionsIndex', [
            'operations' => $operations,
        ]);
    }

    public function show(Operation $operation)
    {
        $operation->load([
            'participants.user:id,display_name,name',
        ]);

        $participants = $operation->participants;

        $participantsBySlot = [];
        foreach ($operation->slots ?? [] as $slotName) {
            $participantsBySlot[$slotName] = $participants->where('slot', $slotName)->values();
        }

        $unassignedParticipants = $participants->whereNull('slot')->values();
        $currentParticipant = $participants->firstWhere('user_id', auth()->id());

        return Inertia::render('Operations/MissionShow', [
            'operation' => $operation,
            'participants' => $participants,
            'participantsBySlot' => $participantsBySlot,
            'unassignedParticipants' => $unassignedParticipants,
            'currentParticipant' => $currentParticipant,
            'authUser' => auth()->user(),
        ]);
    }

    public function create($squadron)
    {
        return Inertia::render('Operations/MissionEditor', [
            'mission' => null,
            'squadronId' => (int) $squadron,
        ]);
    }

    public function edit(Operation $operation)
    {
        return Inertia::render('Operations/MissionEditor', [
            'mission' => $operation,
            'squadronId' => $operation->squadron_id,
        ]);
    }

    public function store(Request $request, Squadron $squadron)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'operation_kind' => 'required|in:event,mission',
            'type' => 'nullable|string|max:255',
            'visibility' => 'required|in:open,squadron,private',
            'difficulty' => 'nullable|in:low,medium,high',
            'operation_strictness' => 'nullable|in:casual,normal,strict,roleplay',
            'rsvp_deadline' => 'nullable|date',
            'icon' => 'nullable|string|max:50',
            'image_url' => 'nullable|string|max:2048',
            'notes' => 'nullable|string',
            'slots' => 'array',
        ]);

        $operation = Operation::create([
            ...$data,
            'squadron_id' => $squadron->id,
            'created_by' => auth()->id(),
            'status' => 'draft',
        ]);

        return redirect()->route('operations.show', $operation->id);
    }

    /**
     * UPDATE EXISTING OPERATION
     */
    public function update(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'operation_kind' => 'required|in:event,mission',
            'type' => 'nullable|string|max:255',
            'visibility' => 'required|in:open,squadron,private',
            'difficulty' => 'nullable|in:low,medium,high',
            'operation_strictness' => 'nullable|in:casual,normal,strict,roleplay',
            'rsvp_deadline' => 'nullable|date',
            'icon' => 'nullable|string|max:50',
            'image_url' => 'nullable|string|max:2048',
            'notes' => 'nullable|string',
            'slots' => 'array',
            'status' => 'required|in:draft,published,completed,cancelled',
        ]);

        $operation->update($data);

        return redirect()->route('operations.show', $operation->id)
            ->with('success', 'Operation updated.');
    }
}
