<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Squadron;
use App\Models\Role;
use App\Models\SquadronMember;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;

class AdminController extends Controller
{
    use AuthorizesRequests;

    /**
     * UNIFIED ADMIN DASHBOARD
     */
    public function dashboard(Request $request)
    {
        $this->authorize('access-admin-panel');

        $search = trim((string) $request->input('search', ''));
        $searchNeedle = $search !== '' ? '%'.mb_strtolower($search).'%' : null;

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
            ->when($searchNeedle, function ($query) use ($search, $searchNeedle) {
                $query->where(function ($q) use ($search, $searchNeedle) {
                    $q->whereRaw('LOWER(discord_name) LIKE ?', [$searchNeedle])
                      ->orWhereRaw('LOWER(rsi_handle) LIKE ?', [$searchNeedle]);

                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
            })
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $squadrons = Squadron::query()
            ->leftJoin('squadron_members as leader_member', function ($join) {
                $join->on('leader_member.squadron_id', '=', 'squadrons.id')
                    ->where('leader_member.role', '=', SquadronMember::ROLE_LEADER)
                    ->where('leader_member.membership_status', '=', SquadronMember::STATUS_ACTIVE);
            })
            ->leftJoin('users as leader_user', 'leader_user.id', '=', 'leader_member.user_id')
            ->orderBy('squadrons.name')
            ->get([
                'squadrons.id',
                'squadrons.name',
                'squadrons.slug',
                'squadrons.status',

                'leader_user.id as leader_id',
                'leader_user.discord_name as leader_discord_name',
                'leader_user.rsi_handle as leader_rsi_handle',
                'leader_user.rank as leader_rank',
                'leader_user.rank_level as leader_rank_level',
            ])
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'slug' => $row->slug,
                    'status' => $row->status,
                    'leader_id' => $row->leader_id,
                    'leader' => $row->leader_id ? [
                        'id' => $row->leader_id,
                        'discord_name' => $row->leader_discord_name,
                        'rsi_handle' => $row->leader_rsi_handle,
                        'rank' => $row->leader_rank,
                        'rank_level' => $row->leader_rank_level,
                    ] : null,
                ];
            })
            ->values();

        $eligibleLeaders = User::where('rank_level', '>=', 3)
            ->select('id', 'discord_name', 'rsi_handle', 'rank', 'rank_level')
            ->orderByDesc('rank_level')
            ->orderBy('discord_name')
            ->get();

        $roles = Role::select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'users'     => $users,
            'squadrons' => $squadrons,
            'roles'     => $roles,
            'eligibleLeaders' => $eligibleLeaders,
            'filters'   => [
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

        $rsiHandle = trim((string) $request->input('rsi_handle', ''));
        $request->merge([
            'rsi_handle' => $rsiHandle !== '' ? $rsiHandle : null,
        ]);

        $data = $request->validate([
            'id'                  => ['required', 'exists:users,id'],
            'rsi_handle'           => [
                'nullable',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-zA-Z0-9-_]+$/',
                Rule::unique('users', 'rsi_handle')->ignore($request->input('id')),
            ],
            'rank'                => ['nullable', 'string', 'max:255'],
            'rank_level'          => ['nullable', 'integer', 'min:1'],
            'global_status'       => ['nullable', 'string', 'max:255'],
            'bio'                 => ['nullable', 'string'],
            'timezone'            => ['nullable', 'string', 'max:255'],
            'availability_status' => ['nullable', 'string', 'max:255'],
            'loa_note'            => ['nullable', 'string'],
        ]);

        $user = User::findOrFail($data['id']);
        $user->fill($data)->save();

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'User updated.');
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

        $user = User::findOrFail($data['id']);
        $user->roles()->sync($data['role_ids'] ?? []);

        Cache::forget("user_roles_{$user->id}");
        Cache::forget("user_permissions_{$user->id}");

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Roles updated successfully.');
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
            'leader_id' => [
                'nullable',
                'exists:users,id',
                function ($attr, $value, $fail) {
                    if ($value) {
                        $leader = \App\Models\User::find($value);
                        // Commander and above = rank_level >= 3
                        if (! $leader || $leader->rank_level < 3) {
                            $fail('Selected leader does not have sufficient rank.');
                        }
                    }
                },
            ],
        ]);

        $squadron = Squadron::create([
            'name'   => $data['name'],
            'slug'   => $data['slug'],
            'status' => $data['status'],
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
    public function updateSquadron(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id'        => ['required', 'exists:squadrons,id'],
            'name'      => ['required', 'string', 'max:255'],
            'slug'      => ['required', 'string', 'max:255'],
            'status'    => ['required', 'in:active,inactive,disbanded'],
            'leader_id' => [
                'nullable',
                'exists:users,id',
                function ($attr, $value, $fail) {
                    if ($value) {
                        $leader = \App\Models\User::find($value);
                        if (! $leader || $leader->rank_level < 3) {
                            $fail('Selected leader does not have sufficient rank.');
                        }
                    }
                },
            ],
        ]);

        $squadron = Squadron::findOrFail($data['id']);
        $previousLeaderId = $squadron->leader_id;
        $newLeaderId = $data['leader_id'] ?? null;

        // Only update fields that actually exist on squadrons table
        $squadron->update([
            'name'   => $data['name'],
            'slug'   => $data['slug'],
            'status' => $data['status'],
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
    public function deleteSquadron(Request $request)
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

        return redirect()
            ->route('admin.squadrons.index')
            ->with('success', 'User added to squadron.');
    }

    /**
     * UPDATE SQUADRON MEMBER
     */
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

        return redirect()
            ->route('admin.squadrons.index')
            ->with('success', 'Member updated.');
    }

    /**
     * REMOVE SQUADRON MEMBER
     */
    public function removeSquadronMember(Request $request)
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

    public function storeRole(Request $request)
    {
        $this->authorize('access-admin-panel');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('roles', 'slug')],
        ]);

        Role::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);

        return redirect()
            ->back()
            ->with('success', 'Role created.');
    }

    public function updateRole(Request $request)
    {
        $this->authorize('access-admin-panel');

        $data = $request->validate([
            'id' => ['required', 'exists:roles,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'slug')->ignore($request->input('id')),
            ],
        ]);

        $role = Role::findOrFail($data['id']);
        $role->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);

        return redirect()
            ->back()
            ->with('success', 'Role updated.');
    }

    public function deleteRole(Request $request)
    {
        $this->authorize('access-admin-panel');

        $data = $request->validate([
            'id' => ['required', 'exists:roles,id'],
        ]);

        $role = Role::findOrFail($data['id']);

        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();

        return redirect()
            ->back()
            ->with('success', 'Role deleted.');
    }

    /**
     * SQUADRONS LIST PAGE
     */
    public function squadronsIndex(Request $request)
    {
        $this->authorize('access-admin-panel');

        $squadrons = Squadron::orderBy('name')->get();

        $eligibleLeaders = User::where('rank_level', '>=', 3)
            ->select('id', 'discord_name', 'rank', 'rank_level')
            ->orderByDesc('rank_level')
            ->orderBy('discord_name')
            ->get();

        return Inertia::render('Admin/SquadronsIndex', [
            'squadrons'       => $squadrons,
            'eligibleLeaders' => $eligibleLeaders,
        ]);
    }

}
