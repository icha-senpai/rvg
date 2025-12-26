<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\Squadron;
use App\Http\Requests\Operations\OperationStoreRequest;
use App\Http\Requests\Operations\OperationUpdateRequest;

// Services & Presenters
use App\Domain\Operations\Services\OperationService;
use App\Domain\Operations\Services\ParticipantService;
use App\Domain\Operations\Presenters\OperationPresenter;
use App\Domain\Operations\Queries\OperationQuery;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;

class OperationPageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OperationService    $service,
        protected OperationQuery      $query,
        protected ParticipantService  $participants
    ) {}

    /* ============================================================
     | INDEX (ALL OPS)
     * ============================================================ */
    public function index(Request $request)
    {
        $operations = Operation::orderByDesc('starts_at')
            ->with(['squadron.leader', 'creator'])
            ->paginate(12)
            ->withQueryString();

        $operations->setCollection(
            $operations->getCollection()
                ->map(fn ($op) => OperationPresenter::make($op)->summary())
        );

        return Inertia::render('Operations/MissionsIndex', [
            'operations' => $operations,
        ]);
    }

    /* ============================================================
     | SHOW SINGLE OPERATION
     * ============================================================ */
    public function show(Request $request, Operation $operation)
    {
        $this->authorize('view', $operation);

        // Load full graph for display
        $operation = $this->service->loadGraph($operation);
        $user = $request->user();

        $participants = $operation->participants;

        // Group participants by slot
        $participantsBySlot = $participants->groupBy(function ($p) {
            return $p->slot ?: 'unassigned';
        });

        $unassigned = $participants->filter(fn ($p) => !$p->slot)->values();

        $currentParticipant = $participants->firstWhere('user_id', $user->id);

        return Inertia::render('Operations/MissionShow', [
            'operation'              => OperationPresenter::make($operation)->full(),
            'authUser'               => $user,
            'participants'           => $participants,
            'participantsBySlot'     => $participantsBySlot,
            'unassignedParticipants' => $unassigned,
            'currentParticipant'     => $currentParticipant,
        ]);
    }

    /* ============================================================
     | CREATE
     * ============================================================ */
    public function createGlobal()
    {
        $this->authorize('create', Operation::class);

        return Inertia::render('Operations/MissionEditor', [
            'mission'    => null,
            'squadronId' => null,
        ]);
    }

    public function storeGlobal(OperationStoreRequest $request)
    {
        $this->authorize('create', Operation::class);

        $operation = $this->service->create($request->validated(), null);

        return Inertia::location(route('operations.show', $operation->id));
    }

    public function create($squadronId)
    {
        $this->authorize('create', [Operation::class, Squadron::findOrFail((int) $squadronId)]);

        return Inertia::render('Operations/MissionEditor', [
            'mission'    => null,
            'squadronId' => (int) $squadronId,
        ]);
    }

    public function store(OperationStoreRequest $request, Squadron $squadron)
    {
        $this->authorize('create', [Operation::class, $squadron]);

        $operation = $this->service->create($request->validated(), $squadron);

        return Inertia::location(route('operations.show', $operation->id));
    }

    /* ============================================================
     | EDIT / UPDATE
     * ============================================================ */
    public function edit(Operation $operation)
    {
        $this->authorize('update', $operation);

        return Inertia::render('Operations/MissionEditor', [
            'mission'    => OperationPresenter::make($operation)->form(),
            'squadronId' => $operation->squadron_id,
        ]);
    }

    public function update(OperationUpdateRequest $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $updated = $this->service->update($operation, $request->validated());

        return redirect()
            ->route('operations.show', $updated->id)
            ->with('success', 'Operation updated.');
    }

    /* ============================================================
     | MEMBER INDEX (visible ops)
     * ============================================================ */
    public function memberIndex(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('operations.index');
        }

        $operations = $this->query->forUser($user)
            ->withQueryString();

        return Inertia::render('Operations/MemberIndex', [
            'operations' => $operations,
        ]);
    }

    /* ============================================================
     | PUBLISH
     * ============================================================ */
    public function publish(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        Log::info("🟢 publish() endpoint hit for operation {$operation->id}");

        $updated = $this->service->transition($operation, 'published');

        return redirect()
            ->route('operations.show', $operation->id)
            ->with('success', 'Operation published successfully.');
    }

    public function showData(Operation $operation)
    {
        $operation->load([
            'squadron',
            'creator',
            'participants.user',
        ]);

        $participants = $operation->participants;

        $participantsBySlot = $participants
            ->whereNotNull('slot')
            ->groupBy('slot');

        $unassignedParticipants = $participants
            ->whereNull('slot')
            ->values();

        $currentParticipant = $participants
            ->firstWhere('user_id', auth()->id());

        return response()->json([
            'operation' => $operation,
            'participants' => $participants,
            'participantsBySlot' => $participantsBySlot,
            'unassignedParticipants' => $unassignedParticipants,
            'currentParticipant' => $currentParticipant,
        ]);
    }

    /* ============================================================
     | DELETE
     * ============================================================ */
    public function destroy(Operation $operation)
    {
        $this->authorize('delete', $operation);

        $operation->delete();

        return back()->with('success', 'Operation deleted.');
    }

    /* ============================================================
     | PARTICIPANT ACTIONS (JOIN / LEAVE / UPDATE SLOT)
     * ============================================================ */

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

        return redirect()
            ->route('operations.show', $operation->id)
            ->with('reload', true);
    }

    public function leave(Request $request, Operation $operation)
    {
        $this->authorize('view', $operation);

        $this->participants->leave($operation, $request->user());

        return redirect()
            ->route('operations.show', $operation->id)
            ->with('reload', true);
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

        return redirect()
            ->route('operations.show', $operation->id)
            ->with('reload', true);
    }
}
