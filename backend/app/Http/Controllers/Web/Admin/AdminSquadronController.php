<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Domain\AccessControl\RoleHierarchy;
use App\Domain\Media\Presenters\MediaPresenter;
use App\Models\User;
use App\Models\Squadron;
use App\Models\SquadronMember;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AdminSquadronController extends Controller
{
    use AuthorizesRequests;

    /**
     * SQUADRONS LIST PAGE
     */
    public function index(Request $request)
    {
        $this->authorize('access-admin-panel');

        $squadrons = Squadron::orderBy('name')->get();

        $eligibleLeaders = $this->getEligibleLeaders();

        return Inertia::render('Admin/SquadronsIndex', [
            'squadrons'       => $squadrons,
            'eligibleLeaders' => $eligibleLeaders,
        ]);
    }

    /**
     * CREATE SQUADRON
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
                function ($attr, $value, $fail) {
                    if ($value) {
                        $leader = User::with('roles:id,slug')->find($value);
                        if (! $leader || ! RoleHierarchy::userAtLeast($leader, 'commander')) {
                            $fail('Selected leader does not have sufficient rank.');
                        }
                    }
                },
            ],
        ]);

        if (! $this->isValidSquadronBranchDivision($data['branch'] ?? null, $data['division'] ?? null)) {
            return back()->withErrors([
                'division' => 'Selected division is not valid for the chosen branch.',
            ]);
        }

        $squadron = Squadron::create([
            'name'   => $data['name'],
            'slug'   => $data['slug'],
            'status' => $data['status'],
            'branch' => $data['branch'] ?? null,
            'division' => $data['division'] ?? null,
        ]);

        // Assign leader via squadron_members
        if (!empty($data['leader_id'])) {
            $squadron->update(['leader_id' => $data['leader_id']]);

            SquadronMember::updateOrCreate(
                [
                    'user_id'     => $data['leader_id'],
                    'squadron_id' => $squadron->id,
                ],
                [
                    'membership_status' => 'active',
                    'role'              => 'leader',
                    'joined_at'         => now(),
                ]
            );
        }

        return redirect()->route('admin.dashboard')
        ->with('success', 'Squadron updated.');

    }


    /**
     * UPDATE SQUADRON
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
                function ($attr, $value, $fail) {
                    if ($value) {
                        $leader = User::with('roles:id,slug')->find($value);
                        if (! $leader || ! RoleHierarchy::userAtLeast($leader, 'commander')) {
                            $fail('Selected leader does not have sufficient rank.');
                        }
                    }
                },
            ],
        ]);

        if (! $this->isValidSquadronBranchDivision($data['branch'] ?? null, $data['division'] ?? null)) {
            return back()->withErrors([
                'division' => 'Selected division is not valid for the chosen branch.',
            ]);
        }

        $squadron = Squadron::findOrFail($data['id']);
        $previousLeaderId = $squadron->leader_id;
        $newLeaderId = $data['leader_id'] ?? null;

        // Only update fields that actually exist on squadrons table
        $squadron->update([
            'name'   => $data['name'],
            'slug'   => $data['slug'],
            'status' => $data['status'],
            'branch' => $data['branch'] ?? null,
            'division' => $data['division'] ?? null,
            'leader_id' => $newLeaderId,
        ]);

        if ($previousLeaderId !== $newLeaderId) {
            SquadronMember::where('squadron_id', $squadron->id)
                ->where('role', SquadronMember::ROLE_LEADER)
                ->delete();

            if ($previousLeaderId) {
                SquadronMember::where('squadron_id', $squadron->id)
                    ->where('user_id', $previousLeaderId)
                    ->delete();
            }
        }

        // Assign new leader if provided
        if (!empty($data['leader_id'])) {
            SquadronMember::updateOrCreate(
                [
                    'user_id'     => $data['leader_id'],
                    'squadron_id' => $squadron->id,
                ],
                [
                    'membership_status' => 'active',
                    'role'              => 'leader',
                    'joined_at'         => now(),
                ]
            );
        }

        return redirect()->route('admin.dashboard')
            ->with('success', 'Squadron updated.');

    }


    /**
     * DELETE SQUADRON
     */
    public function destroy(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id' => ['required', 'exists:squadrons,id'],
        ]);

        Squadron::findOrFail($data['id'])->delete();

        return redirect()->route('admin.dashboard')
            ->with('success', 'Squadron deleted.');

    }

    /**
     * ADD SQUADRON MEMBER
     */
    public function addMember(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'squadron_id' => ['required', 'exists:squadrons,id'],
            'user_id'     => ['required', 'exists:users,id'],
        ]);

        SquadronMember::create([
            'squadron_id'       => $data['squadron_id'],
            'user_id'           => $data['user_id'],
            'membership_status' => 'active',
            'role'              => null,
            'joined_at'         => now(),
        ]);

        return redirect()
            ->route('admin.squadrons.index')
            ->with('success', 'User added to squadron.');
    }

    /**
     * UPDATE SQUADRON MEMBER
     */
    public function updateMember(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id'                => ['required', 'exists:squadron_members,id'],
            'role'              => ['nullable', 'string', 'in:leader,lieutenant,null'],
            'membership_status' => ['required', 'string', 'in:active,pending,banned'],
        ]);

        $member = SquadronMember::findOrFail($data['id']);

        $member->update([
            'role'              => $data['role'] === 'null' ? null : $data['role'],
            'membership_status' => $data['membership_status'],
        ]);

        return redirect()
            ->route('admin.squadrons.index')
            ->with('success', 'Member updated.');
    }

    /**
     * REMOVE SQUADRON MEMBER
     */
    public function removeMember(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id' => ['required', 'exists:squadron_members,id'],
        ]);

        SquadronMember::findOrFail($data['id'])->delete();

        return redirect()
            ->route('admin.squadrons.index')
            ->with('success', 'Member removed.');
    }

    /**
     * Get users eligible to be squadron leaders.
     */
    private function getEligibleLeaders()
    {
        $eligibleLeaderRoleSlugs = ['commander', 'wing_commander', 'admiral', 'grand_admiral', 'director', 'tech_director'];

        return User::whereHas('roles', function ($q) use ($eligibleLeaderRoleSlugs) {
                $q->whereIn('slug', $eligibleLeaderRoleSlugs);
            })
            ->select('id', 'discord_name', 'rsi_handle', 'rank', 'rank_level')
            ->orderByDesc('rank_level')
            ->orderBy('discord_name')
            ->get();
    }

    private function isValidSquadronBranchDivision(?string $branch, ?string $division): bool
    {
        if (! $branch && ! $division) return true;
        if (! $branch || ! $division) return false;

        $map = [
            'defence' => ['marines', 'navy', 'airforce'],
            'industries' => ['procurement', 'logistics', 'construction'],
            'frontiers' => ['exploration', 'science', 'development'],
            'lifeline' => ['triage', 'recovery', 'medical'],
        ];

        return array_key_exists($branch, $map)
            && in_array($division, $map[$branch], true);
    }
}
