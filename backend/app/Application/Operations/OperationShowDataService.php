<?php

namespace App\Application\Operations;

use App\Application\Operations\Presenters\OperationPresenter;
use App\Domain\AccessControl\AccessService;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Support\Collection;

class OperationShowDataService
{
    public function __construct(
        protected AccessService $access
    ) {}

    public function build(Operation $operation, ?User $viewer = null): array
    {
        $operation->load([
            'squadron',
            'squadron.emblem',
            'creator',
            'participants.user',
            'participants.role',
            'images',
        ]);

        $canViewSlots = $viewer ? $this->access->canViewOperationSlots($viewer, $operation) : false;
        $canAssignSlots = $viewer ? $this->access->canAssignOperationSlots($viewer, $operation) : false;
        $canManageAar = $viewer ? $this->access->canManageAfterActionReport($viewer, $operation) : false;

        $participantCount = $operation->participants->count();
        $participants = $canViewSlots
            ? $this->participantsPayload($operation, true)
            : collect();
        $currentParticipant = null;

        if ($viewer) {
            $currentParticipantModel = $operation->participants->firstWhere('user_id', $viewer->id);

            if ($currentParticipantModel) {
                $currentParticipant = $this->participantPayload($currentParticipantModel, true);
            }
        }

        $operationPayload = OperationPresenter::make($operation)->full();
        $operationPayload['participants'] = $participants->values()->all();
        $operationPayload['participants_count'] = $participantCount;
        $operationPayload['after_action_attendance'] = $this->attendancePayload($operation);
        $operationPayload['after_action_no_show'] = $this->noShowPayload($operation);
        $operationPayload['permissions'] = [
            'can_view_slots' => $canViewSlots,
            'can_assign_slots' => $canAssignSlots,
            'can_manage_aar' => $canManageAar,
        ];

        return [
            'operation' => $operationPayload,
            'participants' => $participants->values()->all(),
            'participantsBySlot' => $canViewSlots
                ? $participants
                    ->filter(fn (array $participant) => filled($participant['slot']))
                    ->groupBy(fn (array $participant) => $participant['slot'])
                    ->all()
                : [],
            'unassignedParticipants' => $canViewSlots
                ? $participants->filter(fn (array $participant) => blank($participant['slot']))->values()->all()
                : [],
            'currentParticipant' => $currentParticipant,
            'verifiedMembers' => $canManageAar ? $this->verifiedMembersPayload() : [],
        ];
    }

    public function editor(Operation $operation): array
    {
        return [
            'mission' => OperationPresenter::make($operation)->form(),
            'squadronId' => $operation->squadron_id,
        ];
    }

    public function dashboardAfterActionOperations(User $viewer, int $limit = 24): array
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
            ->limit($limit)
            ->get();

        return $operations
            ->map(function (Operation $operation) use ($viewer) {
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
                    'after_action_attendance' => $this->attendancePayload($operation),
                    'after_action_no_show' => $this->noShowPayload($operation),
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
                    'permissions' => [
                        'can_manage_aar' => $this->access->canManageAfterActionReport($viewer, $operation),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    public function verifiedMembers(): array
    {
        return $this->verifiedMembersPayload();
    }

    protected function participantsPayload(Operation $operation, bool $canViewSlots): Collection
    {
        return $operation->participants
            ->map(fn ($participant) => $this->participantPayload($participant, $canViewSlots))
            ->values();
    }

    protected function participantPayload($participant, bool $includeSlot): array
    {
        return [
            'id' => $participant->id,
            'slot' => $includeSlot ? $participant->slot : null,
            'role' => $participant->role ? [
                'id' => $participant->role->id,
                'role_name' => $participant->role->role_name,
                'role_display_name' => $participant->role->role_display_name,
                'capacity' => $participant->role->capacity,
            ] : null,
            'attendance_status' => $participant->attendance_status,
            'notes' => $participant->notes,
            'user' => [
                'id' => $participant->user?->id,
                'rsi_handle' => $participant->user?->rsi_handle,
                'display_name' => $participant->user?->display_name,
                'name' => $participant->user?->name,
                'discord_avatar' => $participant->user?->discord_avatar,
                'avatar' => $participant->user?->avatar,
            ],
        ];
    }

    protected function attendancePayload(Operation $operation): array
    {
        return $this->memberPayloadsForIds($operation->after_action_attendance_user_ids ?? []);
    }

    protected function noShowPayload(Operation $operation): array
    {
        return $this->memberPayloadsForIds($operation->after_action_no_show_user_ids ?? []);
    }

    protected function memberPayloadsForIds(array $ids): array
    {
        $memberIds = collect($ids)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($memberIds->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->select('id', 'rsi_handle', 'discord_name', 'discord_avatar', 'name')
            ->whereIn('id', $memberIds->all())
            ->get()
            ->keyBy('id');

        return $memberIds
            ->map(fn (int $id) => $users->get($id))
            ->filter()
            ->map(fn (User $user) => $this->memberPayload($user))
            ->values()
            ->all();
    }

    protected function verifiedMembersPayload(): array
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
