<?php

namespace App\Services;

use App\Application\Operations\OperationShowDataService;
use App\Application\Operations\OperationMemberPayloadService;
use App\Domain\Media\Presenters\MediaPresenter;
use App\Domain\Squadrons\SquadronService;
use App\Models\Operation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdminDashboardService
{
    public function __construct(
        protected SquadronService $squadrons,
        protected LedgerService $ledger,
        protected OperationShowDataService $operationShowData,
        protected OperationMemberPayloadService $members,
        protected AdminArchiveStatsService $archiveStats,
        protected AdminUexStatusService $uexStatus,
    ) {}

    public function build(string $search): array
    {
        return [
            'users' => $this->users($search),
            'squadrons' => $this->dashboardSquadrons(),
            'roles' => $this->roles(),
            'operations' => $this->completedOperations(),
            'canceledOperations' => $this->canceledOperations(),
            'operationSettlementLootOptions' => $this->operationShowData->settlementLootOptions(),
            'verifiedMembers' => $this->operationShowData->verifiedMembers(),
            'archiveStats' => $this->archiveStats->build(),
            'uex' => $this->uexStatus->build(),
            'ledger' => $this->ledger->buildAdminData(),
            'eligibleLeaders' => $this->squadrons->eligibleLeaders(),
            'filters' => [
                'search' => $search,
            ],
        ];
    }

    protected function users(string $search)
    {
        $searchNeedle = $search !== '' ? '%' . mb_strtolower($search) . '%' : null;
        $normalizedSearch = trim((string) preg_replace('/\s+/', ' ', str_replace('_', ' ', mb_strtolower($search))));
        $normalizedSearchNeedle = $normalizedSearch !== '' ? '%' . $normalizedSearch . '%' : null;

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
                'region',
                'timezone',
                'availability_status',
                'loa_note'
            )
            ->with(['roles:id,name,slug'])
            ->when($searchNeedle, function ($query) use ($search, $searchNeedle, $normalizedSearchNeedle) {
                $query->where(function ($nested) use ($search, $searchNeedle, $normalizedSearchNeedle) {
                    $nested->whereRaw('LOWER(discord_name) LIKE ?', [$searchNeedle])
                        ->orWhereRaw('LOWER(rsi_handle) LIKE ?', [$searchNeedle])
                        ->orWhereRaw('LOWER(rank) LIKE ?', [$searchNeedle])
                        ->orWhereRaw("CASE WHEN rank = 'cit' THEN 'c i t commander in training' ELSE REPLACE(LOWER(rank), '_', ' ') END LIKE ?", [$normalizedSearchNeedle ?? $searchNeedle])
                        ->orWhereHas('roles', function ($roles) use ($searchNeedle, $normalizedSearchNeedle) {
                            $roles->whereRaw('LOWER(name) LIKE ?', [$searchNeedle])
                                ->orWhereRaw('LOWER(slug) LIKE ?', [$searchNeedle])
                                ->orWhereRaw("REPLACE(LOWER(slug), '_', ' ') LIKE ?", [$normalizedSearchNeedle ?? $searchNeedle]);
                        });

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
                $rosterSummary = $this->squadrons->rosterConsistencySummary($squadron);

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
                    'discord_channel_id' => $squadron->discord_channel_id,
                    'discord_sync_status' => $squadron->discord_sync_status,
                    'discord_last_synced_at' => $squadron->discord_last_synced_at?->toIso8601String(),
                    'discord_sync_error' => $squadron->discord_sync_error,
                    'roster_status' => $rosterSummary['status'] ?? 'ok',
                    'roster_issue' => $rosterSummary['message'] ?? null,
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

    protected function completedOperations(): array
    {
        $viewer = Auth::user();

        return $viewer
            ? $this->operationShowData->dashboardAfterActionOperations($viewer, 24)
            : [];
    }

    protected function canceledOperations(): array
    {
        return Operation::query()
            ->select(
                'id',
                'created_by',
                'squadron_id',
                'squadron_name',
                'title',
                'description',
                'starts_at',
                'ends_at',
                'status',
                'cancellation_reason'
            )
            ->with([
                'creator:id,rsi_handle,discord_name,discord_avatar,name',
                'squadron:id,name',
            ])
            ->where('status', 'canceled')
            ->orderByRaw('COALESCE(ends_at, starts_at) DESC')
            ->orderByDesc('id')
            ->limit(24)
            ->get()
            ->map(function (Operation $operation) {
                return [
                    'id' => $operation->id,
                    'title' => $operation->title,
                    'description' => $operation->description,
                    'starts_at' => $operation->starts_at?->toIso8601String(),
                    'ends_at' => $operation->ends_at?->toIso8601String(),
                    'status' => $operation->status,
                    'cancellation_reason' => filled($operation->cancellation_reason)
                        ? $operation->cancellation_reason
                        : 'No cancellation reason recorded.',
                    'creator' => $operation->creator ? $this->memberPayload($operation->creator) : null,
                    'squadron' => $operation->squadron
                        ? [
                            'id' => $operation->squadron->id,
                            'name' => $operation->squadron->name,
                        ]
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    protected function memberPayload(User $user): array
    {
        return $this->members->payload($user);
    }
}
