<?php

namespace App\Services;

use App\Domain\Media\Presenters\MediaPresenter;
use App\Domain\Squadrons\SquadronService;
use App\Models\Role;
use App\Models\User;

class AdminDashboardService
{
    public function __construct(
        protected SquadronService $squadrons,
    ) {}

    public function build(string $search): array
    {
        return [
            'users' => $this->users($search),
            'squadrons' => $this->dashboardSquadrons(),
            'roles' => $this->roles(),
            'eligibleLeaders' => $this->squadrons->eligibleLeaders(),
            'filters' => [
                'search' => $search,
            ],
        ];
    }

    protected function users(string $search)
    {
        $searchNeedle = $search !== '' ? '%' . mb_strtolower($search) . '%' : null;

        return User::query()
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
                $query->where(function ($nested) use ($search, $searchNeedle) {
                    $nested->whereRaw('LOWER(discord_name) LIKE ?', [$searchNeedle])
                        ->orWhereRaw('LOWER(rsi_handle) LIKE ?', [$searchNeedle]);

                    if (is_numeric($search)) {
                        $nested->orWhere('id', (int) $search);
                    }
                });
            })
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();
    }

    protected function dashboardSquadrons(): array
    {
        return $this->squadrons->listAll()
            ->map(function ($squadron) {
                return [
                    'id' => $squadron->id,
                    'name' => $squadron->name,
                    'slug' => $squadron->slug,
                    'status' => $squadron->status,
                    'branch' => $squadron->branch,
                    'division' => $squadron->division,
                    'emblem_url' => $squadron->emblem
                        ? $squadron->emblem->display_url
                        : ($squadron->emblem_path ? asset('storage/' . $squadron->emblem_path) : null),
                    'emblem' => $squadron->emblem
                        ? MediaPresenter::make($squadron->emblem)->embedded()
                        : null,
                    'leader_id' => $squadron->leader_id,
                    'leader' => $squadron->leader
                        ? [
                            'id' => $squadron->leader->id,
                            'discord_name' => $squadron->leader->discord_name,
                            'rsi_handle' => $squadron->leader->rsi_handle,
                            'rank' => $squadron->leader->rank,
                            'rank_level' => $squadron->leader->rank_level,
                            'roles' => $squadron->leader->roles->map(fn ($role) => [
                                'slug' => $role->slug,
                                'name' => $role->name,
                            ])->values(),
                        ]
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    protected function roles()
    {
        return Role::select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();
    }
}
