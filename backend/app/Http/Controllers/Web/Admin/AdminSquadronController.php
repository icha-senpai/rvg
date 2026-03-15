<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Domain\Squadrons\MembershipService;
use App\Domain\Squadrons\SquadronService;
use App\Models\Squadron;
use App\Models\SquadronMember;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Handles admin-only squadron management screens and mutation actions.
 */
class AdminSquadronController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected SquadronService $squadrons,
        protected MembershipService $membership,
    ) {}

    /**
     * Render the admin squadron index with eligible leader choices.
     */
    public function index(Request $request)
    {
        $this->authorize('access-admin-panel');

        return Inertia::render('Admin/SquadronsIndex', [
            'squadrons'       => $this->squadrons->listAll(),
            'eligibleLeaders' => $this->squadrons->eligibleLeaders(),
        ]);
    }

    /**
     * Create a squadron from the admin dashboard.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'slug'      => ['required', 'string', 'max:255', 'unique:squadrons,slug'],
            'status'    => ['required', 'in:active,inactive,disbanded'],
            'branch'    => ['nullable', 'string', 'in:defence,industries,frontiers,lifeline'],
            'division'  => ['nullable', 'string', 'in:marines,navy,airforce,procurement,logistics,construction,exploration,science,development,triage,recovery,medical'],
            'leader_id' => [
                'nullable',
                'exists:users,id',
            ],
        ]);

        $this->squadrons->assertEligibleLeader(isset($data['leader_id']) ? (int) $data['leader_id'] : null);
        $this->squadrons->createForAdmin($data);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Squadron updated.');
    }

    /**
     * Update an existing squadron from the admin dashboard.
     */
    public function update(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id'        => ['required', 'exists:squadrons,id'],
            'name'      => ['required', 'string', 'max:255'],
            'slug'      => ['required', 'string', 'max:255'],
            'status'    => ['required', 'in:active,inactive,disbanded'],
            'branch'    => ['nullable', 'string', 'in:defence,industries,frontiers,lifeline'],
            'division'  => ['nullable', 'string', 'in:marines,navy,airforce,procurement,logistics,construction,exploration,science,development,triage,recovery,medical'],
            'leader_id' => [
                'nullable',
                'exists:users,id',
            ],
        ]);

        $this->squadrons->assertEligibleLeader(isset($data['leader_id']) ? (int) $data['leader_id'] : null);
        $this->squadrons->updateForAdmin(Squadron::findOrFail($data['id']), $data);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Squadron updated.');
    }

    /**
     * Delete a squadron from the admin dashboard.
     */
    public function destroy(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id' => ['required', 'exists:squadrons,id'],
        ]);

        $this->squadrons->delete(Squadron::findOrFail($data['id']));

        return redirect()->route('admin.dashboard')
            ->with('success', 'Squadron deleted.');
    }

    /**
     * Add a user directly to a squadron from the admin dashboard.
     */
    public function addMember(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'squadron_id' => ['required', 'exists:squadrons,id'],
            'user_id'     => ['required', 'exists:users,id'],
        ]);

        $this->membership->adminCreateMember(
            Squadron::findOrFail($data['squadron_id']),
            (int) $data['user_id']
        );

        return redirect()
            ->route('admin.squadrons.index')
            ->with('success', 'User added to squadron.');
    }

    /**
     * Update one squadron membership record from the admin dashboard.
     */
    public function updateMember(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id'                => ['required', 'exists:squadron_members,id'],
            'role'              => ['nullable', 'string', 'in:leader,lieutenant,null'],
            'membership_status' => ['required', 'string', 'in:active,pending,banned'],
        ]);

        $this->membership->adminUpdateMember(
            SquadronMember::findOrFail($data['id']),
            $data['role'] ?? null,
            $data['membership_status']
        );

        return redirect()
            ->route('admin.squadrons.index')
            ->with('success', 'Member updated.');
    }

    /**
     * Remove a squadron membership record from the admin dashboard.
     */
    public function removeMember(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id' => ['required', 'exists:squadron_members,id'],
        ]);

        $this->membership->adminDeleteMember(SquadronMember::findOrFail($data['id']));

        return redirect()
            ->route('admin.squadrons.index')
            ->with('success', 'Member removed.');
    }
}
