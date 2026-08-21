<?php

namespace App\Application\Operations;

use App\Domain\AccessControl\AccessService;
use App\Models\Operation;
use App\Models\OperationSettlement;
use App\Models\Squadron;
use App\Models\User;
use App\Services\LedgerReferenceService;
use Illuminate\Support\Facades\Schema;

class OperationSettlementViewService
{
    protected ?bool $settlementStorageAvailable = null;

    public function __construct(
        protected AccessService $access,
        protected LedgerReferenceService $ledgerReferences,
        protected OperationMemberPayloadService $members,
    ) {}

    public function payload(
        Operation $operation,
        ?User $viewer = null,
        ?bool $canManage = null,
        ?array $lootOptions = null,
        bool $includeLootOptions = true
    ): array {
        if (! $this->supportsStorage()) {
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
        $lootOptions ??= $this->lootOptions();
        $settlement = $operation->relationLoaded('settlement')
            ? $operation->getRelation('settlement')
            : $operation->settlement()->first();
        $settlement?->loadMissing([
            'finalizedBy:id,rsi_handle,discord_name,name',
            'reopenedBy:id,rsi_handle,discord_name,name',
        ]);
        $eligibleRecipients = $this->eligibleRecipients($operation);
        $recipientLabels = collect($eligibleRecipients)
            ->mapWithKeys(fn (array $recipient) => [$recipient['key'] => $recipient['label']])
            ->all();

        return [
            'money_rows' => $this->moneyRows($settlement, $recipientLabels),
            'loot_rows' => $this->lootRows($settlement, $lootOptions, $recipientLabels),
            'eligible_recipients' => $eligibleRecipients,
            'loot_options' => $includeLootOptions ? $lootOptions : null,
            'permissions' => [
                'can_manage' => $canManage,
            ],
            'is_available' => true,
            'is_finalized' => (bool) $settlement?->finalized_at,
            'locked_attendance' => (bool) $settlement?->finalized_at,
            'activity' => $this->activityPayload($settlement),
            'finalized_at' => $settlement?->finalized_at?->toIso8601String(),
            'updated_at' => $settlement?->updated_at?->toIso8601String(),
        ];
    }

    public function prepPayload(Operation $operation): array
    {
        if (! $this->supportsStorage()) {
            return [
                'money_rows' => [],
                'eligible_recipients' => [],
                'activity' => [
                    'updated_at' => null,
                    'updated_by' => null,
                ],
                'is_available' => false,
            ];
        }

        $settlement = $operation->relationLoaded('settlement')
            ? $operation->getRelation('settlement')
            : $operation->settlement()->first();
        $settlement?->loadMissing([
            'prepUpdatedBy:id,rsi_handle,discord_name,name',
        ]);

        $eligibleRecipients = $this->prepEligibleRecipients($operation);
        $recipientLabels = collect($eligibleRecipients)
            ->mapWithKeys(fn (array $recipient) => [$recipient['key'] => $recipient['label']])
            ->all();

        return [
            'money_rows' => $this->prepMoneyRows($settlement, $recipientLabels),
            'eligible_recipients' => $eligibleRecipients,
            'activity' => [
                'updated_at' => $settlement?->prep_money_rows_updated_at?->toIso8601String(),
                'updated_by' => $this->members->displayName($settlement?->prepUpdatedBy),
            ],
            'is_available' => true,
        ];
    }

    public function lootOptions(): array
    {
        $itemOptions = collect($this->ledgerReferences->itemReferenceOptions())
            ->map(fn (array $item) => [
                'value' => (string) ($item['uex_id'] ?? ''),
                'label' => trim(($item['name'] ?? 'Unknown item').(! empty($item['type']) ? " · {$item['type']}" : '')),
            ])
            ->filter(fn (array $item) => $item['value'] !== '')
            ->values()
            ->all();

        return [
            'commodities' => collect($this->ledgerReferences->commodityReferenceOptions())
                ->map(fn (array $commodity) => [
                    'value' => (string) ($commodity['uex_id'] ?? ''),
                    'label' => $commodity['name'] ?? 'Unknown commodity',
                ])
                ->filter(fn (array $commodity) => $commodity['value'] !== '')
                ->values()
                ->all(),
            'items' => $itemOptions,
            'components' => [],
        ];
    }

    public function supportsStorage(): bool
    {
        return $this->settlementStorageAvailable ??= Schema::hasTable('operation_settlements');
    }

    protected function activityPayload(?OperationSettlement $settlement): array
    {
        return [
            'updated_at' => $settlement?->updated_at?->toIso8601String(),
            'finalized_at' => $settlement?->finalized_at?->toIso8601String(),
            'finalized_by' => $this->members->displayName($settlement?->finalizedBy),
            'reopened_at' => $settlement?->reopened_at?->toIso8601String(),
            'reopened_by' => $this->members->displayName($settlement?->reopenedBy),
        ];
    }

    protected function eligibleRecipients(Operation $operation): array
    {
        $attendance = $this->members->payloadsForIds($operation->after_action_attendance_user_ids ?? []);
        $recipients = collect();

        foreach ($this->eligibleSquadrons($operation) as $squadron) {
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

    protected function prepEligibleRecipients(Operation $operation): array
    {
        $operation->loadMissing('participants.user');
        $recipients = collect();

        foreach ($this->eligibleSquadrons($operation) as $squadron) {
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

        $members = $operation->participants
            ->reject(fn ($participant) => $participant->isSignedOffBeforeStart())
            ->filter(fn ($participant) => $participant->user)
            ->map(fn ($participant) => $this->members->payload($participant->user))
            ->unique(fn ($member) => (int) ($member['id'] ?? 0))
            ->values();

        foreach ($members as $member) {
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

    protected function moneyRows(?OperationSettlement $settlement, array $recipientLabels): array
    {
        return collect($settlement?->money_rows ?? [])
            ->map(function (array $row) use ($recipientLabels) {
                $key = $this->recipientKey(
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

    protected function prepMoneyRows(?OperationSettlement $settlement, array $recipientLabels): array
    {
        return collect($settlement?->prep_money_rows ?? [])
            ->map(function (array $row) use ($recipientLabels) {
                $key = $this->recipientKey(
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

    protected function lootRows(?OperationSettlement $settlement, array $lootOptions, array $recipientLabels): array
    {
        $lootLabels = collect($lootOptions['commodities'] ?? [])
            ->merge($lootOptions['items'] ?? [])
            ->mapWithKeys(fn (array $option) => [(string) $option['value'] => $option['label']])
            ->all();

        return collect($settlement?->loot_rows ?? [])
            ->map(function (array $row) use ($lootLabels, $recipientLabels) {
                $recipientKey = $this->recipientKey(
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

    protected function recipientKey(?string $recipientType, $recipientUserId, $recipientSquadronId = null): ?string
    {
        return match ($recipientType) {
            'member' => filled($recipientUserId) ? 'member:'.(int) $recipientUserId : null,
            'squadron' => filled($recipientSquadronId) ? 'squadron:'.(int) $recipientSquadronId : null,
            'organization' => 'organization',
            default => null,
        };
    }

    protected function eligibleSquadrons(Operation $operation)
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
