<?php

namespace App\Application\Operations;

use App\Application\Operations\Presenters\OperationPresenter;
use App\Application\Operations\Presenters\OperationPresenterRelations;
use App\Domain\AccessControl\AccessService;
use App\Models\Operation;
use App\Models\User;

class OperationShowDataService
{
    public function __construct(
        protected AccessService $access,
        protected OperationMemberPayloadService $members,
        protected OperationParticipantPayloadService $participants,
        protected OperationSettlementViewService $settlements,
    ) {}

    public function build(Operation $operation, ?User $viewer = null): array
    {
        OperationPresenterRelations::loadForFull($operation);
        $operation->loadMissing(['participants.role']);

        if ($this->settlements->supportsStorage()) {
            $operation->loadMissing('settlement');
        }

        $canViewSlots = $viewer ? $this->access->canViewOperationSlots($viewer, $operation) : false;
        $canAssignSlots = $viewer ? $this->access->canAssignOperationSlots($viewer, $operation) : false;
        $canManageAar = $viewer ? $this->access->canManageAfterActionReport($viewer, $operation) : false;
        $settlementLootOptions = $this->settlementLootOptions();

        $participantCount = $operation->participants->count();
        $participants = $this->participants->participants($operation, $canViewSlots);
        $currentParticipant = null;

        if ($viewer) {
            $currentParticipantModel = $operation->participants->firstWhere('user_id', $viewer->id);

            if ($currentParticipantModel) {
                $currentParticipant = $this->participants->participant($currentParticipantModel, true);
            }
        }

        $operationPayload = OperationPresenter::make($operation)->full();
        $operationPayload['participants'] = $participants->values()->all();
        $operationPayload['participants_count'] = $participantCount;
        $operationPayload['after_action_attendance'] = $this->members->payloadsForIds($operation->after_action_attendance_user_ids ?? []);
        $operationPayload['after_action_no_show'] = $this->members->payloadsForIds($operation->after_action_no_show_user_ids ?? []);
        $operationPayload['operation_settlement'] = $this->settlements->payload($operation, $viewer, $canManageAar, $settlementLootOptions);
        $operationPayload['permissions'] = [
            'can_view_slots' => $canViewSlots,
            'can_assign_slots' => $canAssignSlots,
            'can_manage_aar' => $canManageAar,
            'can_manage_settlement' => $canManageAar,
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
            'verifiedMembers' => $canManageAar ? $this->members->verifiedMembers() : [],
        ];
    }

    public function editor(Operation $operation): array
    {
        OperationPresenterRelations::loadForForm($operation);

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
                'settlement',
            ])
            ->where('status', 'completed')
            ->whereNotNull('completion_outcome')
            ->orderByRaw('COALESCE(ends_at, starts_at) DESC')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $settlementLootOptions = $this->settlementLootOptions();

        return $operations
            ->map(function (Operation $operation) use ($viewer, $settlementLootOptions) {
                $canManage = $this->access->canManageAfterActionReport($viewer, $operation);

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
                    'after_action_attendance' => $this->members->payloadsForIds($operation->after_action_attendance_user_ids ?? []),
                    'after_action_no_show' => $this->members->payloadsForIds($operation->after_action_no_show_user_ids ?? []),
                    'operation_settlement' => $this->settlements->payload($operation, $viewer, $canManage, $settlementLootOptions, false),
                    'creator' => $operation->creator ? $this->members->payload($operation->creator) : null,
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
                            'user' => $participant->user ? $this->members->payload($participant->user) : null,
                        ])
                        ->values()
                        ->all(),
                    'permissions' => [
                        'can_manage_aar' => $canManage,
                        'can_manage_settlement' => $canManage,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    public function verifiedMembers(): array
    {
        return $this->members->verifiedMembers();
    }

    public function settlementPayload(
        Operation $operation,
        ?User $viewer = null,
        ?bool $canManage = null,
        ?array $lootOptions = null,
        bool $includeLootOptions = true
    ): array {
        return $this->settlements->payload($operation, $viewer, $canManage, $lootOptions, $includeLootOptions);
    }

    public function settlementLootOptions(): array
    {
        return $this->settlements->lootOptions();
    }
}
