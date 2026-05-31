<?php

namespace App\Services;

use App\Domain\Media\Presenters\MediaPresenter;
use App\Domain\Squadrons\SquadronService;
use App\Models\Operation;
use App\Models\ArchiveCategory;
use App\Models\ArchiveEntry;
use App\Models\ArchiveTag;
use App\Models\ArchiveTopic;
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
            'operations' => $this->completedOperations(),
            'canceledOperations' => $this->canceledOperations(),
            'verifiedMembers' => $this->verifiedMembers(),
            'archiveStats' => $this->archiveStats(),
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

    protected function completedOperations(): array
    {
        $operations = Operation::query()
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
                'completion_outcome',
                'after_action_report',
                'after_action_attendance_user_ids',
                'after_action_no_show_user_ids',
                'after_action_report_updated_at'
            )
            ->with([
                'creator:id,rsi_handle,discord_name,discord_avatar,name',
                'participants.user:id,rsi_handle,discord_name,discord_avatar,name',
                'squadron:id,name',
            ])
            ->where('status', 'completed')
            ->whereNotNull('completion_outcome')
            ->orderByRaw('COALESCE(ends_at, starts_at) DESC')
            ->orderByDesc('id')
            ->limit(24)
            ->get();

        $attendanceUsersById = $this->attendanceUsersFor($operations);

        return $operations
            ->map(function (Operation $operation) use ($attendanceUsersById) {
                $attendance = collect($operation->after_action_attendance_user_ids ?? [])
                    ->map(fn ($id) => $attendanceUsersById[(int) $id] ?? null)
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'id' => $operation->id,
                    'title' => $operation->title,
                    'description' => $operation->description,
                    'starts_at' => $operation->starts_at?->toIso8601String(),
                    'ends_at' => $operation->ends_at?->toIso8601String(),
                    'status' => $operation->status,
                    'completion_outcome' => $operation->completion_outcome,
                    'after_action_report' => $operation->after_action_report,
                    'after_action_attendance_user_ids' => $operation->after_action_attendance_user_ids ?? [],
                    'after_action_no_show_user_ids' => $operation->after_action_no_show_user_ids ?? [],
                    'after_action_report_updated_at' => $operation->after_action_report_updated_at?->toIso8601String(),
                    'after_action_attendance' => $attendance,
                    'after_action_no_show' => collect($operation->after_action_no_show_user_ids ?? [])
                        ->map(fn ($id) => $attendanceUsersById[(int) $id] ?? null)
                        ->filter()
                        ->values()
                        ->all(),
                    'creator' => $operation->creator ? $this->memberPayload($operation->creator) : null,
                    'squadron' => $operation->squadron
                        ? [
                            'id' => $operation->squadron->id,
                            'name' => $operation->squadron->name,
                        ]
                        : null,
                    'participants' => $operation->participants
                        ->map(fn ($participant) => [
                            'id' => $participant->id,
                            'slot' => $participant->slot,
                            'user' => $participant->user ? $this->memberPayload($participant->user) : null,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
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

    protected function verifiedMembers(): array
    {
        return User::query()
            ->select('id', 'rsi_handle', 'discord_name', 'discord_avatar', 'name')
            ->where('global_status', User::STATUS_ACTIVE)
            ->whereNotNull('rsi_verified_at')
            ->orderByRaw("LOWER(COALESCE(rsi_handle, discord_name, name, ''))")
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => $this->memberPayload($user))
            ->values()
            ->all();
    }

    protected function archiveStats(): array
    {
        $deletedTopics = ArchiveTopic::onlyTrashed()->count();
        $deletedEntries = ArchiveEntry::onlyTrashed()->count();
        $deletedCategories = ArchiveCategory::onlyTrashed()->count();
        $deletedTags = ArchiveTag::onlyTrashed()->count();

        return [
            'topics' => ArchiveTopic::query()->count(),
            'entries' => ArchiveEntry::query()->count(),
            'categories' => ArchiveCategory::query()->count(),
            'tags' => ArchiveTag::query()->count(),
            'trash_total' => $deletedTopics + $deletedEntries + $deletedCategories + $deletedTags,
            'trash' => [
                'topics' => $deletedTopics,
                'entries' => $deletedEntries,
                'categories' => $deletedCategories,
                'tags' => $deletedTags,
            ],
        ];
    }

    protected function attendanceUsersFor($operations): array
    {
        $attendanceIds = $operations
            ->flatMap(fn (Operation $operation) => array_merge(
                $operation->after_action_attendance_user_ids ?? [],
                $operation->after_action_no_show_user_ids ?? []
            ))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($attendanceIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->select('id', 'rsi_handle', 'discord_name', 'discord_avatar', 'name')
            ->whereIn('id', $attendanceIds->all())
            ->get()
            ->mapWithKeys(fn (User $user) => [
                $user->id => $this->memberPayload($user),
            ])
            ->all();
    }

    protected function memberPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'rsi_handle' => $user->rsi_handle,
            'discord_name' => $user->discord_name,
            'discord_avatar' => $user->discord_avatar,
            'name' => $user->name,
        ];
    }
}
