<?php

namespace App\Application\Operations;

use App\Application\Operations\Presenters\OperationPresenter;
use App\Domain\AccessControl\AccessService;
use App\Models\Operation;
use App\Models\OperationSettlement;
use App\Models\Squadron;
use App\Models\User;
use App\Services\LedgerReferenceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OperationShowDataService
{
    protected ?bool $settlementStorageAvailable = null;

    public function __construct(
        protected AccessService $access,
        protected LedgerReferenceService $ledgerReferences,
    ) {}

    public function build(Operation $operation, ?User $viewer = null): array
    {
        $relations = [
            'squadron',
            'squadron.emblem',
            'creator',
            'participants.user',
            'participants.role',
            'images',
        ];

        if ($this->supportsSettlementStorage()) {
            $relations[] = 'settlement';
        }

        $operation->load($relations);

        $canViewSlots = $viewer ? $this->access->canViewOperationSlots($viewer, $operation) : false;
        $canAssignSlots = $viewer ? $this->access->canAssignOperationSlots($viewer, $operation) : false;
        $canManageAar = $viewer ? $this->access->canManageAfterActionReport($viewer, $operation) : false;
        $settlementLootOptions = $this->settlementLootOptions();

        $participantCount = $operation->participants->count();
        $participants = $this->participantsPayload($operation, $canViewSlots);
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
        $operationPayload['operation_settlement'] = $this->settlementPayload($operation, $viewer, $canManageAar, $settlementLootOptions);
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
        $relations = [
            'creator:id,rsi_handle,discord_name,discord_avatar,name',
            'participants.user:id,rsi_handle,discord_name,discord_avatar,name',
            'squadron:id,name',
        ];

        if ($this->supportsSettlementStorage()) {
            $relations[] = 'settlement';
        }

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
            ->with($relations)
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
                    'after_action_attendance' => $this->attendancePayload($operation),
                    'after_action_no_show' => $this->noShowPayload($operation),
                    'operation_settlement' => $this->settlementPayload($operation, $viewer, $canManage, $settlementLootOptions, false),
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
        return $this->verifiedMembersPayload();
    }

    protected function participantsPayload(Operation $operation, bool $canViewSlots): Collection
    {
        return $operation->participants
            ->map(fn ($participant) => $this->participantPayload($participant, $canViewSlots))
            ->values();
    }

    protected function participantPayload($participant, bool $includeAssignments): array
    {
        return [
            'id' => $participant->id,
            'slot' => $includeAssignments ? $participant->slot : null,
            'role' => $includeAssignments && $participant->role ? [
                'id' => $participant->role->id,
                'role_name' => $participant->role->role_name,
                'role_display_name' => $participant->role->role_display_name,
                'capacity' => $participant->role->capacity,
            ] : null,
            'attendance_status' => $includeAssignments ? $participant->attendance_status : null,
            'notes' => $includeAssignments ? $participant->notes : null,
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

    protected function memberDisplayName(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return $user->rsi_handle
            ?? $user->discord_name
            ?? $user->name
            ?? "Member #{$user->id}";
    }

    public function settlementPayload(
        Operation $operation,
        ?User $viewer = null,
        ?bool $canManage = null,
        ?array $lootOptions = null,
        bool $includeLootOptions = true
    ): array {
        if (! $this->supportsSettlementStorage()) {
            return [
                'money_rows' => [],
                'loot_rows' => [],
                'eligible_recipients' => [],
                'loot_options' => $includeLootOptions ? [
                    'commodities' => [],
                    'items' => [],
                    'components' => [],
                ] : null,
                'permissions' => [
                    'can_manage' => false,
                ],
                'is_available' => false,
                'is_finalized' => false,
                'locked_attendance' => false,
                'activity' => [
                    'updated_at' => null,
                    'finalized_at' => null,
                    'finalized_by' => null,
                    'reopened_at' => null,
                    'reopened_by' => null,
                ],
                'finalized_at' => null,
                'updated_at' => null,
            ];
        }

        $canManage ??= $viewer ? $this->access->canManageAfterActionReport($viewer, $operation) : false;
        $lootOptions ??= $this->settlementLootOptions();
        $settlement = $operation->relationLoaded('settlement')
            ? $operation->getRelation('settlement')
            : $operation->settlement()->first();
        $settlement?->loadMissing([
            'finalizedBy:id,rsi_handle,discord_name,name',
            'reopenedBy:id,rsi_handle,discord_name,name',
        ]);
        $eligibleRecipients = $this->settlementEligibleRecipients($operation);
        $recipientLabels = collect($eligibleRecipients)
            ->mapWithKeys(fn (array $recipient) => [$recipient['key'] => $recipient['label']])
            ->all();

        return [
            'money_rows' => $this->settlementMoneyRows($settlement, $recipientLabels),
            'loot_rows' => $this->settlementLootRows($settlement, $lootOptions, $recipientLabels),
            'eligible_recipients' => $eligibleRecipients,
            'loot_options' => $includeLootOptions ? $lootOptions : null,
            'permissions' => [
                'can_manage' => $canManage,
            ],
            'is_available' => true,
            'is_finalized' => (bool) $settlement?->finalized_at,
            'locked_attendance' => (bool) $settlement?->finalized_at,
            'activity' => $this->settlementActivityPayload($settlement),
            'finalized_at' => $settlement?->finalized_at?->toIso8601String(),
            'updated_at' => $settlement?->updated_at?->toIso8601String(),
        ];
    }

    protected function settlementActivityPayload(?OperationSettlement $settlement): array
    {
        return [
            'updated_at' => $settlement?->updated_at?->toIso8601String(),
            'finalized_at' => $settlement?->finalized_at?->toIso8601String(),
            'finalized_by' => $this->memberDisplayName($settlement?->finalizedBy),
            'reopened_at' => $settlement?->reopened_at?->toIso8601String(),
            'reopened_by' => $this->memberDisplayName($settlement?->reopenedBy),
        ];
    }

    protected function settlementEligibleRecipients(Operation $operation): array
    {
        $attendance = $this->attendancePayload($operation);
        $recipients = collect();

        foreach ($this->settlementEligibleSquadrons($operation) as $squadron) {
            $recipients->push([
                'key' => "squadron:{$squadron->id}",
                'recipient_type' => 'squadron',
                'recipient_user_id' => null,
                'recipient_squadron_id' => $squadron->id,
                'label' => "{$squadron->name} Squadron Assets & Funds",
            ]);
        }

        $recipients->push([
            'key' => 'organization',
            'recipient_type' => 'organization',
            'recipient_user_id' => null,
            'recipient_squadron_id' => null,
            'label' => 'Horizon Treasury',
        ]);

        foreach ($attendance as $member) {
            $name = $member['rsi_handle'] ?? $member['discord_name'] ?? $member['name'] ?? "Member #{$member['id']}";

            $recipients->push([
                'key' => "member:{$member['id']}",
                'recipient_type' => 'member',
                'recipient_user_id' => (int) $member['id'],
                'recipient_squadron_id' => null,
                'label' => $name,
            ]);
        }

        return $recipients->values()->all();
    }

    public function settlementLootOptions(): array
    {
        $references = $this->ledgerReferences->referenceOptions();

        $itemOptions = collect($references['items'] ?? [])
            ->map(fn (array $item) => [
                'value' => (string) ($item['uex_id'] ?? ''),
                'label' => trim(($item['name'] ?? 'Unknown item') . (! empty($item['type']) ? " · {$item['type']}" : '')),
            ])
            ->filter(fn (array $item) => $item['value'] !== '')
            ->values()
            ->all();

        return [
            'commodities' => collect($references['commodities'] ?? [])
                ->map(fn (array $commodity) => [
                    'value' => (string) ($commodity['uex_id'] ?? ''),
                    'label' => $commodity['name'] ?? 'Unknown commodity',
                ])
                ->filter(fn (array $commodity) => $commodity['value'] !== '')
                ->values()
                ->all(),
            'items' => $itemOptions,
            'components' => $itemOptions,
        ];
    }

    protected function settlementMoneyRows(?OperationSettlement $settlement, array $recipientLabels): array
    {
        return collect($settlement?->money_rows ?? [])
            ->map(function (array $row) use ($recipientLabels) {
                $key = $this->settlementRecipientKey(
                    $row['recipient_type'] ?? null,
                    $row['recipient_user_id'] ?? null,
                    $row['recipient_squadron_id'] ?? null,
                );

                return [
                    ...$row,
                    'recipient_label' => $key ? ($recipientLabels[$key] ?? null) : null,
                ];
            })
            ->values()
            ->all();
    }

    protected function supportsSettlementStorage(): bool
    {
        return $this->settlementStorageAvailable ??= Schema::hasTable('operation_settlements');
    }

    protected function settlementLootRows(?OperationSettlement $settlement, array $lootOptions, array $recipientLabels): array
    {
        $lootLabels = collect($lootOptions['commodities'] ?? [])
            ->merge($lootOptions['items'] ?? [])
            ->mapWithKeys(fn (array $option) => [(string) $option['value'] => $option['label']])
            ->all();

        return collect($settlement?->loot_rows ?? [])
            ->map(function (array $row) use ($lootLabels, $recipientLabels) {
                $recipientKey = $this->settlementRecipientKey(
                    $row['recipient_type'] ?? null,
                    $row['recipient_user_id'] ?? null,
                    $row['recipient_squadron_id'] ?? null,
                );

                return [
                    ...$row,
                    'reference_label' => $row['reference_label'] ?? ($lootLabels[(string) ($row['uex_reference_id'] ?? '')] ?? null),
                    'recipient_label' => $recipientKey ? ($recipientLabels[$recipientKey] ?? null) : null,
                ];
            })
            ->values()
            ->all();
    }

    protected function settlementRecipientKey(?string $recipientType, $recipientUserId, $recipientSquadronId = null): ?string
    {
        return match ($recipientType) {
            'member' => filled($recipientUserId) ? 'member:' . (int) $recipientUserId : null,
            'squadron' => filled($recipientSquadronId) ? 'squadron:' . (int) $recipientSquadronId : null,
            'organization' => 'organization',
            default => null,
        };
    }

    protected function settlementEligibleSquadrons(Operation $operation)
    {
        $nameSet = collect(explode(',', (string) ($operation->squadron_name ?? '')))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->values();

        $squadrons = collect();
        $primarySquadron = $operation->relationLoaded('squadron')
            ? $operation->getRelation('squadron')
            : $operation->squadron()->first();

        if ($primarySquadron) {
            $squadrons->push($primarySquadron);
        }

        if ($nameSet->isNotEmpty()) {
            $squadrons = $squadrons->merge(
                Squadron::query()
                    ->whereIn('name', $nameSet->all())
                    ->get()
            );
        }

        return $squadrons
            ->filter(fn ($squadron) => $squadron?->id)
            ->unique(fn ($squadron) => (int) $squadron->id)
            ->values();
    }
}
