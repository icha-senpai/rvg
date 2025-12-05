<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Squadron;
use App\Models\Role;
use App\Models\SquadronMember;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AdminController extends Controller
{
    use AuthorizesRequests;

    /**
     * UNIFIED ADMIN DASHBOARD
     */
    public function dashboard(Request $request)
    {
        $this->authorize('access-admin-panel');

        $search = $request->input('search');

        // USERS — full payload needed by UsersPanel
        $users = User::query()
            ->select(
                'id',
                'rsi_handle',
                'discord_name',
                'rank',
                'rank_level',
                'global_status',
                'bio',
                'timezone',
                'availability_status',
                'loa_note'
            )
            ->with(['roles:id,name,slug'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('discord_name', 'LIKE', "%{$search}%")
                        ->orWhere('rsi_handle', 'LIKE', "%{$search}%")
                        ->orWhere('id', $search);
                });
            })
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        // SQUADRONS LIST
        $squadrons = Squadron::select('id', 'name', 'slug', 'status')
            ->orderBy('name')
            ->get();

        // ROLES LIST
        $roles = Role::select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'users'     => $users,
            'squadrons' => $squadrons,
            'roles'     => $roles,
            'filters'   => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * LEGACY USERS PAGE (kept for routing compatibility)
     */
    public function usersIndex(Request $request)
    {
        $this->authorize('access-admin-panel');

        $search = $request->input('search');

        $users = User::query()
            ->select(
                'id',
                'rsi_handle',
                'discord_name',
                'rank',
                'rank_level',
                'global_status',
                'bio',
                'timezone',
                'availability_status',
                'loa_note'
            )
            ->with(['roles:id,name,slug'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('discord_name', 'LIKE', "%{$search}%")
                        ->orWhere('rsi_handle', 'LIKE', "%{$search}%")
                        ->orWhere('id', $search);
                });
            })
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        $roles = Role::select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/UsersIndex', [
            'users'   => $users,
            'roles'   => $roles,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * UPDATE USER FIELDS
     */
    public function updateUser(Request $request)
    {
        $this->authorize('access-admin-panel');

        $data = $request->validate([
            'id'                   => ['required', 'exists:users,id'],
            'rank'                 => ['nullable', 'string', 'max:255'],
            'rank_level'           => ['nullable', 'integer', 'min:1'],
            'global_status'        => ['nullable', 'string', 'max:255'],
            'bio'                  => ['nullable', 'string'],
            'timezone'             => ['nullable', 'string', 'max:255'],
            'availability_status'  => ['nullable', 'string', 'max:255'],
            'loa_note'             => ['nullable', 'string'],
        ]);

        $user = User::findOrFail($data['id']);

        $user->fill($data)->save();

        return back()->with('success', 'User updated.');
    }

    /**
     * UPDATE USER ROLES
     */
    public function updateUserRoles(Request $request)
    {
        $this->authorize('access-admin-panel');

        $data = $request->validate([
            'id'         => ['required', 'exists:users,id'],
            'role_ids'   => ['array'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        User::findOrFail($data['id'])
            ->roles()
            ->sync($data['role_ids'] ?? []);

        return back()->with('success', 'Roles updated successfully.');
    }

    /**
     * CREATE SQUADRON
     */
    public function storeSquadron(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'slug'      => ['required', 'string', 'max:255', 'unique:squadrons,slug'],
            'status'    => ['required', 'in:active,inactive,disbanded'],
            'leader_id' => ['nullable', 'exists:users,id'],
        ]);

        $squadron = Squadron::create($data);

        if (!empty($data['leader_id'])) {
            SquadronMember::create([
                'user_id'           => $data['leader_id'],
                'squadron_id'       => $squadron->id,
                'membership_status' => 'active',
                'role'              => 'leader',
                'joined_at'         => now(),
            ]);
        }

        return back()->with('success', 'Squadron created.');
    }

    /**
     * UPDATE SQUADRON
     */
    public function updateSquadron(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id'        => ['required', 'exists:squadrons,id'],
            'name'      => ['required', 'string', 'max:255'],
            'slug'      => ['required', 'string', 'max:255'],
            'status'    => ['required', 'in:active,inactive,disbanded'],
            'leader_id' => ['nullable', 'exists:users,id'],
        ]);

        $squadron = Squadron::findOrFail($data['id']);
        $squadron->update($data);

        // Remove old leaders
        SquadronMember::where('squadron_id', $squadron->id)
            ->where('role', 'leader')
            ->update(['role' => null]);

        // Assign new leader
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

        return back()->with('success', 'Squadron updated.');
    }

    /**
     * DELETE SQUADRON
     */
    public function deleteSquadron(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id' => ['required', 'exists:squadrons,id'],
        ]);

        Squadron::findOrFail($data['id'])->delete();

        return back()->with('success', 'Squadron deleted.');
    }

    public function addSquadronMember(Request $request)
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

        return back()->with('success', 'User added to squadron.');
    }

    public function updateSquadronMember(Request $request)
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

        return back()->with('success', 'Member updated.');
    }

    public function removeSquadronMember(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id' => ['required', 'exists:squadron_members,id'],
        ]);

        SquadronMember::findOrFail($data['id'])->delete();

        return back()->with('success', 'Member removed from squadron.');
    }

    public function squadronsIndex(Request $request)
    {
        $this->authorize('access-admin-panel');

        $squadrons = Squadron::orderBy('name')->get();

        return Inertia::render('Admin/SquadronsIndex', [
            'squadrons' => $squadrons
        ]);
    }
}
