<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\Media\Presenters\MediaPresenter;
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

        $search = trim((string) $request->input('search', ''));
        $searchNeedle = $search !== '' ? '%'.mb_strtolower($search).'%' : null;

        $users = User::query()
            ->select(
                'id',
                'rsi_handle',
                'discord_name',
                'discord_avatar',
                'rsi_verified_at',
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

        $rawSquadrons = Squadron::query()
            ->with('emblem')
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
                'squadrons.branch',
                'squadrons.division',
                'squadrons.emblem_path',

                'leader_user.id as leader_id',
                'leader_user.discord_name as leader_discord_name',
                'leader_user.rsi_handle as leader_rsi_handle',
                'leader_user.rank as leader_rank',
                'leader_user.rank_level as leader_rank_level',
            ]);

        $leaderIds = $rawSquadrons
            ->pluck('leader_id')
            ->filter()
            ->unique()
            ->values();

        $leadersById = $leaderIds->isNotEmpty()
            ? User::query()
                ->with(['roles:id,slug,name'])
                ->whereIn('id', $leaderIds)
                ->get()
                ->keyBy('id')
            : collect();

        $squadrons = $rawSquadrons
            ->map(function ($row) use ($leadersById) {
                $emblemUrl = null;

                if ($row->relationLoaded('emblem') && $row->emblem) {
                    $emblemUrl = $row->emblem->display_url;
                } elseif ($row->emblem_path) {
                    $emblemUrl = asset('storage/' . $row->emblem_path);
                }

                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'slug' => $row->slug,
                    'status' => $row->status,
                    'branch' => $row->branch,
                    'division' => $row->division,
                    'emblem_url' => $emblemUrl,
                    'emblem' => ($row->relationLoaded('emblem') && $row->emblem)
                        ? MediaPresenter::make($row->emblem)->embedded()
                        : null,
                    'leader_id' => $row->leader_id,
                    'leader' => $row->leader_id ? [
                        'id' => $row->leader_id,
                        'discord_name' => $row->leader_discord_name,
                        'rsi_handle' => $row->leader_rsi_handle,
                        'rank' => $row->leader_rank,
                        'rank_level' => $row->leader_rank_level,
                        'roles' => $leadersById->get($row->leader_id)
                            ? $leadersById->get($row->leader_id)->roles->map(fn ($role) => [
                                'slug' => $role->slug,
                                'name' => $role->name,
                            ])->values()
                            : [],
                    ] : null,
                ];
            })
            ->values();

        $eligibleLeaderRoleSlugs = ['commander', 'wing_commander', 'admiral', 'grand_admiral', 'director', 'tech_director'];

        $eligibleLeaders = User::whereHas('roles', function ($q) use ($eligibleLeaderRoleSlugs) {
            $q->whereIn('slug', $eligibleLeaderRoleSlugs);
        })
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
}
