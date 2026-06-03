<?php

namespace App\Domain\Promotions;

use App\Domain\AccessControl\AccessService;
use App\Domain\Promotions\Events\PromotionOfferCancelled;
use App\Domain\Promotions\Events\PromotionOfferCreated;
use App\Domain\Promotions\Events\PromotionOfferExpired;
use App\Models\AuthAuditLog;
use App\Models\PromotionOffer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PromotionWorkflowService
{
    public function __construct(
        protected AccessService $access,
    ) {}

    public function profilePayload(?User $viewer, User $member): array
    {
        $viewer?->loadMissing('roles:id,slug,name');
        $member->loadMissing('roles:id,slug,name');

        if (! $this->promotionStorageReady()) {
            return [
                'show_panel' => false,
                'current_rank' => null,
                'next_rank' => null,
                'available' => false,
                'gating_reason' => null,
                'allowed_branch_roles' => [],
                'can_create' => false,
                'can_cancel' => false,
                'can_demote' => false,
                'expiry_minutes' => (int) config('promotions.expires_after_minutes', 240),
                'active_offer' => null,
            ];
        }

        $activeOffer = $this->activePendingOfferFor($member);
        $currentRank = $this->resolveControlledRank($member);
        $nextRank = $activeOffer?->to_rank ?? $this->nextRankFor($member);

        $hasPromotionAuthority = $viewer ? $this->viewerHasPromotionAuthority($viewer) : false;
        $canCancel = $viewer && $activeOffer ? $this->canCancelOffer($viewer, $activeOffer) : false;
        $canDemote = $viewer ? $this->canDemoteMember($viewer, $member) : false;
        $canCreate = false;
        $gatingReason = null;
        $branchOptions = [];

        if ($viewer && ! $viewer->is($member) && $nextRank) {
            $branchOptions = $this->branchOptionsForRank($nextRank);

            if ($this->viewerCanPromoteTo($viewer, $nextRank) && ! $activeOffer) {
                $readiness = $this->configurationReadinessForOffer($member, $nextRank);
                $canCreate = $readiness['ready'];
                $gatingReason = $readiness['reason'];
            } elseif (! $activeOffer && $hasPromotionAuthority) {
                $gatingReason = $this->firstUnavailableReason($member, $viewer, $nextRank);
            }
        }

        $showPanel = (bool) (
            $viewer
            && ! $viewer->is($member)
            && ($hasPromotionAuthority || $canCancel || $canDemote)
            && ($currentRank || $activeOffer)
        );

        return [
            'show_panel' => $showPanel,
            'current_rank' => $currentRank ? [
                'slug' => $currentRank,
                'label' => $this->rankLabel($currentRank),
            ] : null,
            'next_rank' => $nextRank ? [
                'slug' => $nextRank,
                'label' => $this->rankLabel($nextRank),
            ] : null,
            'available' => $canCreate,
            'gating_reason' => $gatingReason,
            'allowed_branch_roles' => $branchOptions,
            'can_create' => $canCreate,
            'can_cancel' => $canCancel,
            'can_demote' => $canDemote,
            'expiry_minutes' => (int) config('promotions.expires_after_minutes', 240),
            'active_offer' => $activeOffer ? $this->presentOffer($activeOffer) : null,
        ];
    }

    public function createOffer(User $viewer, User $member, string $branchRoleId): PromotionOffer
    {
        if (! $this->promotionStorageReady()) {
            $this->throwPromotionError('The promotion workflow is not available until the latest migration has been applied.');
        }

        $viewer->loadMissing('roles:id,slug,name');
        $member->loadMissing('roles:id,slug,name');

        if ($viewer->is($member)) {
            $this->throwPromotionError('You cannot issue a promotion offer to yourself.');
        }

        if ($this->activePendingOfferFor($member)) {
            $this->throwPromotionError('This member already has a pending promotion offer.');
        }

        $currentRank = $this->resolveControlledRank($member);
        $nextRank = $this->nextRankFor($member);

        if (! $currentRank || ! $nextRank) {
            $this->throwPromotionError('This member does not have an eligible next promotion in the controlled ladder.');
        }

        if (! $this->viewerCanPromoteTo($viewer, $nextRank)) {
            $this->throwPromotionError('You are not authorized to issue this promotion offer.');
        }

        $readiness = $this->configurationReadinessForOffer($member, $nextRank);
        if (! $readiness['ready']) {
            $this->throwPromotionError($readiness['reason'] ?? 'This promotion is not currently configured.');
        }

        $allowedBranchIds = collect($this->branchOptionsForRank($nextRank))
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        if (! in_array($branchRoleId, $allowedBranchIds, true)) {
            $this->throwPromotionError('Select one of the allowed branch roles for this promotion.');
        }

        return DB::transaction(function () use ($viewer, $member, $currentRank, $nextRank, $branchRoleId) {
            $offer = PromotionOffer::create([
                'member_id' => $member->id,
                'promoter_id' => $viewer->id,
                'from_rank' => $currentRank,
                'to_rank' => $nextRank,
                'discord_branch_role_id' => $branchRoleId,
                'state' => PromotionOffer::STATE_PENDING,
                'expires_at' => now()->addMinutes((int) config('promotions.expires_after_minutes', 240)),
            ]);

            $this->writeAuditLog('promotion_offer.created', $viewer->id, [
                'offer_id' => $offer->id,
                'member_id' => $member->id,
                'from_rank' => $currentRank,
                'to_rank' => $nextRank,
                'discord_branch_role_id' => $branchRoleId,
            ]);

            event(new PromotionOfferCreated($offer));

            return $offer->fresh(['member.roles:id,slug,name', 'promoter']);
        });
    }

    public function cancelOffer(User $viewer, PromotionOffer $offer): PromotionOffer
    {
        if (! $this->promotionStorageReady()) {
            $this->throwPromotionError('The promotion workflow is not available until the latest migration has been applied.');
        }

        $offer = $offer->fresh(['member.roles:id,slug,name', 'promoter.roles:id,slug,name']) ?? $offer;
        $offer = $this->expireIfStale($offer);

        if ($offer->state !== PromotionOffer::STATE_PENDING) {
            $this->throwPromotionError('That promotion offer is no longer pending.');
        }

        if (! $this->canCancelOffer($viewer, $offer)) {
            $this->throwPromotionError('You are not authorized to cancel this promotion offer.');
        }

        return DB::transaction(function () use ($viewer, $offer) {
            $offer->forceFill([
                'state' => PromotionOffer::STATE_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $viewer->id,
            ])->save();

            $this->writeAuditLog('promotion_offer.cancelled', $viewer->id, [
                'offer_id' => $offer->id,
                'member_id' => $offer->member_id,
                'to_rank' => $offer->to_rank,
            ]);

            event(new PromotionOfferCancelled($offer));

            return $offer->fresh(['member', 'promoter', 'cancelledBy']);
        });
    }

    public function demoteToMember(User $viewer, User $member): User
    {
        if (! $this->promotionStorageReady()) {
            $this->throwPromotionError('The promotion workflow is not available until the latest migration has been applied.');
        }

        $viewer->loadMissing('roles:id,slug,name');
        $member->loadMissing('roles:id,slug,name');

        if (! $this->canDemoteMember($viewer, $member)) {
            $this->throwPromotionError('You are not authorized to demote this member.');
        }

        return DB::transaction(function () use ($viewer, $member) {
            if ($pending = $this->activePendingOfferFor($member)) {
                $pending->forceFill([
                    'state' => PromotionOffer::STATE_CANCELLED,
                    'cancelled_at' => now(),
                    'cancelled_by_user_id' => $viewer->id,
                ])->save();

                $this->writeAuditLog('promotion_offer.cancelled', $viewer->id, [
                    'offer_id' => $pending->id,
                    'member_id' => $member->id,
                    'to_rank' => $pending->to_rank,
                    'reason' => 'superseded_by_demotion',
                ]);

                event(new PromotionOfferCancelled($pending));
            }

            $this->syncApplicationRank($member, 'member');

            $this->writeAuditLog('promotion.demoted', $viewer->id, [
                'member_id' => $member->id,
                'to_rank' => 'member',
            ]);

            return $member->fresh(['roles:id,slug,name', 'squadrons:id,name']);
        });
    }

    public function prepareAcceptance(PromotionOffer $offer, string $discordId, ?string $messageId = null): array
    {
        if (! $this->promotionStorageReady()) {
            $this->throwPromotionError('The promotion workflow is not available until the latest migration has been applied.');
        }

        $offer = $offer->fresh(['member.roles:id,slug,name', 'promoter']) ?? $offer;
        $offer = $this->expireIfStale($offer);

        if ($offer->state === PromotionOffer::STATE_ACCEPTED) {
            return [
                'already_accepted' => true,
                'offer_id' => $offer->id,
                'accepted_at' => optional($offer->accepted_at)->toIso8601String(),
            ];
        }

        if ($offer->state !== PromotionOffer::STATE_PENDING) {
            $this->throwPromotionError('This promotion offer is no longer pending.');
        }

        if ((string) $offer->member?->discord_id !== (string) $discordId) {
            $this->throwPromotionError('Only the intended Discord member can accept this promotion.');
        }

        if ($messageId && (string) $offer->discord_dm_message_id !== (string) $messageId) {
            $this->throwPromotionError('This reaction does not match the tracked promotion DM.');
        }

        $readiness = $this->configurationReadinessForOffer($offer->member, $offer->to_rank);
        if (! $readiness['ready']) {
            $this->throwPromotionError($readiness['reason'] ?? 'This promotion is no longer configured for automated delivery.');
        }

        $targetRankRoleId = $this->discordRankRoleId($offer->to_rank);
        $branchRoleLabel = $this->branchLabelForRole($offer->to_rank, $offer->discord_branch_role_id);
        $memberName = $offer->member?->rsi_handle ?? $offer->member?->discord_name ?? $offer->member?->name ?? 'Member';
        $addRoleIds = array_values(array_unique(array_filter([
            $targetRankRoleId,
            $offer->discord_branch_role_id,
        ])));

        return [
            'already_accepted' => false,
            'offer_id' => $offer->id,
            'member_id' => $offer->member_id,
            'member_discord_id' => $offer->member?->discord_id,
            'member_name' => $memberName,
            'dm_message_id' => $offer->discord_dm_message_id,
            'quarter_message_id' => $offer->discord_quarter_message_id,
            'quarter_channel_id' => $this->quarterChannelId($offer->to_rank),
            'to_rank' => $offer->to_rank,
            'to_rank_label' => $this->rankLabel($offer->to_rank),
            'branch_role_id' => $offer->discord_branch_role_id,
            'branch_role_label' => $branchRoleLabel,
            'accept_emoji' => (string) config('promotions.accept_emoji', '✅'),
            'add_role_ids' => $addRoleIds,
            'remove_role_ids' => $this->discordRoleIdsToRemoveForAcceptance($offer->to_rank, $offer->discord_branch_role_id),
        ];
    }

    public function finalizeAcceptance(PromotionOffer $offer, string $discordId, ?string $messageId = null): PromotionOffer
    {
        if (! $this->promotionStorageReady()) {
            $this->throwPromotionError('The promotion workflow is not available until the latest migration has been applied.');
        }

        $offer = $offer->fresh(['member.roles:id,slug,name', 'promoter']) ?? $offer;
        $offer = $this->expireIfStale($offer);

        if ($offer->state === PromotionOffer::STATE_ACCEPTED) {
            return $offer;
        }

        if ($offer->state !== PromotionOffer::STATE_PENDING) {
            $this->throwPromotionError('This promotion offer is no longer pending.');
        }

        if ((string) $offer->member?->discord_id !== (string) $discordId) {
            $this->throwPromotionError('Only the intended Discord member can finalize this promotion.');
        }

        if ($messageId && (string) $offer->discord_dm_message_id !== (string) $messageId) {
            $this->throwPromotionError('This finalize request does not match the tracked promotion DM.');
        }

        return DB::transaction(function () use ($offer) {
            $this->syncApplicationRank($offer->member, $offer->to_rank);

            $offer->forceFill([
                'state' => PromotionOffer::STATE_ACCEPTED,
                'accepted_at' => now(),
            ])->save();

            $this->writeAuditLog('promotion_offer.accepted', $offer->member_id, [
                'offer_id' => $offer->id,
                'member_id' => $offer->member_id,
                'from_rank' => $offer->from_rank,
                'to_rank' => $offer->to_rank,
                'via' => 'discord_reaction',
            ]);

            return $offer->fresh(['member.roles:id,slug,name', 'promoter']);
        });
    }

    public function expireDueOffers(): int
    {
        if (! $this->promotionStorageReady()) {
            return 0;
        }

        $count = 0;

        PromotionOffer::query()
            ->where('state', PromotionOffer::STATE_PENDING)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->get()
            ->each(function (PromotionOffer $offer) use (&$count) {
                $this->expireOffer($offer);
                $count++;
            });

        return $count;
    }

    public function activePendingOfferFor(User $member): ?PromotionOffer
    {
        if (! $this->promotionStorageReady()) {
            return null;
        }

        $offer = PromotionOffer::query()
            ->with(['member.roles:id,slug,name', 'promoter'])
            ->where('member_id', $member->id)
            ->where('state', PromotionOffer::STATE_PENDING)
            ->latest('id')
            ->first();

        if (! $offer) {
            return null;
        }

        $offer = $this->expireIfStale($offer);

        return $offer->state === PromotionOffer::STATE_PENDING ? $offer : null;
    }

    public function presentOffer(PromotionOffer $offer): array
    {
        return [
            'id' => $offer->id,
            'state' => $offer->state,
            'from_rank' => [
                'slug' => $offer->from_rank,
                'label' => $this->rankLabel($offer->from_rank),
            ],
            'to_rank' => [
                'slug' => $offer->to_rank,
                'label' => $this->rankLabel($offer->to_rank),
            ],
            'branch_role_id' => $offer->discord_branch_role_id,
            'branch_role_label' => $this->branchLabelForRole($offer->to_rank, $offer->discord_branch_role_id),
            'promoter' => [
                'id' => $offer->promoter?->id,
                'name' => $offer->promoter?->rsi_handle ?? $offer->promoter?->discord_name ?? $offer->promoter?->name ?? 'Unknown',
            ],
            'created_at' => optional($offer->created_at)->toIso8601String(),
            'created_at_label' => optional($offer->created_at)->format('M j, Y g:i A T'),
            'expires_at' => optional($offer->expires_at)->toIso8601String(),
            'expires_at_label' => optional($offer->expires_at)->format('M j, Y g:i A T'),
            'expires_in_label' => $offer->expires_at ? $offer->expires_at->diffForHumans() : null,
        ];
    }

    public function discordCreatePayload(PromotionOffer $offer): array
    {
        $offer->loadMissing(['member.roles:id,slug,name', 'promoter']);

        $memberName = $offer->member?->rsi_handle ?? $offer->member?->discord_name ?? $offer->member?->name ?? 'Member';
        $promoterName = $offer->promoter?->rsi_handle ?? $offer->promoter?->discord_name ?? $offer->promoter?->name ?? 'Command';
        $promoterRankLabel = $this->rankLabel($this->resolveControlledRank($offer->promoter) ?? $offer->promoter?->rank);
        $promoterMention = $offer->promoter?->discord_id
            ? "<@{$offer->promoter->discord_id}>"
            : $promoterName;
        $rankLabel = $this->rankLabel($offer->to_rank);
        $branchLabel = $this->branchLabelForRole($offer->to_rank, $offer->discord_branch_role_id) ?? 'Assigned Branch';
        $replacements = [
            'member_name' => $memberName,
            'promoter_name' => $promoterName,
            'promoter_rank_label' => $promoterRankLabel,
            'promoter_mention' => $promoterMention,
            'current_rank' => $this->rankLabel($offer->from_rank),
            'rank_label' => $rankLabel,
            'branch_label' => $branchLabel,
            'accept_emoji' => (string) config('promotions.accept_emoji', '✅'),
            'expires_at' => optional($offer->expires_at)->format('M j, Y g:i A T') ?? 'the expiry window',
        ];

        return [
            'offer_id' => $offer->id,
            'to_rank' => $offer->to_rank,
            'member_discord_id' => $offer->member?->discord_id,
            'member_name' => $memberName,
            'promoter_name' => $promoterName,
            'promoter_mention' => $promoterMention,
            'from_rank_label' => $this->rankLabel($offer->from_rank),
            'to_rank_label' => $rankLabel,
            'branch_role_id' => $offer->discord_branch_role_id,
            'branch_role_label' => $branchLabel,
            'quarter_channel_id' => $this->quarterChannelId($offer->to_rank),
            'accept_emoji' => (string) config('promotions.accept_emoji', '✅'),
            'expires_at' => optional($offer->expires_at)->toIso8601String(),
            'expires_at_label' => $replacements['expires_at'],
            'dm_content' => $this->renderTemplate((string) config("promotions.dm_templates.{$offer->to_rank}", ''), $replacements),
            'quarter_content' => $this->renderTemplate((string) config("promotions.quarter_templates.{$offer->to_rank}", ''), $replacements),
        ];
    }

    public function discordStatusPayload(PromotionOffer $offer, string $status): array
    {
        $offer->loadMissing(['member', 'promoter']);

        $memberName = $offer->member?->rsi_handle ?? $offer->member?->discord_name ?? $offer->member?->name ?? 'Member';
        $promoterName = $offer->promoter?->rsi_handle ?? $offer->promoter?->discord_name ?? $offer->promoter?->name ?? 'Command';
        $branchLabel = $this->branchLabelForRole($offer->to_rank, $offer->discord_branch_role_id) ?? 'Assigned Branch';
        $statusLabel = match ($status) {
            PromotionOffer::STATE_CANCELLED => 'cancelled',
            PromotionOffer::STATE_EXPIRED => 'expired',
            default => $status,
        };

        return [
            'offer_id' => $offer->id,
            'status' => $status,
            'to_rank' => $offer->to_rank,
            'member_discord_id' => $offer->member?->discord_id,
            'member_name' => $memberName,
            'promoter_name' => $promoterName,
            'promoter_mention' => $offer->promoter?->discord_id ? "<@{$offer->promoter->discord_id}>" : $promoterName,
            'to_rank_label' => $this->rankLabel($offer->to_rank),
            'branch_role_label' => $branchLabel,
            'quarter_channel_id' => $this->quarterChannelId($offer->to_rank),
            'dm_message_id' => $offer->discord_dm_message_id,
            'quarter_message_id' => $offer->discord_quarter_message_id,
            'dm_update_content' => "{$memberName}'s promotion offer to {$this->rankLabel($offer->to_rank)} has been {$statusLabel}.",
            'quarter_update_content' => "{$memberName}'s promotion offer to {$this->rankLabel($offer->to_rank)} under {$branchLabel} has been {$statusLabel}.",
        ];
    }

    public function rankLabel(?string $rank): string
    {
        if (! $rank) {
            return 'Unknown';
        }

        return (string) (config("promotions.labels.{$rank}") ?? str($rank)->replace('_', ' ')->title()->value());
    }

    protected function nextRankFor(User $member): ?string
    {
        $current = $this->resolveControlledRank($member);
        if (! $current) {
            return null;
        }

        $ladder = array_values(config('promotions.ladder', []));
        $index = array_search($current, $ladder, true);

        if ($index === false) {
            return null;
        }

        return $ladder[$index + 1] ?? null;
    }

    protected function resolveControlledRank(User $member): ?string
    {
        $validRanks = array_values(config('promotions.ladder', []));
        $authorityRanks = ['director', 'tech_director'];
        $accepted = array_merge($validRanks, $authorityRanks);

        if (is_string($member->rank) && in_array($member->rank, $accepted, true)) {
            return $member->rank;
        }

        $member->loadMissing('roles:id,slug,name');

        $sorted = collect($member->roles ?? [])
            ->map(fn ($role) => $role->slug)
            ->filter(fn ($slug) => in_array($slug, $accepted, true))
            ->sortByDesc(function (string $slug) use ($accepted) {
                return array_search($slug, $accepted, true);
            })
            ->values();

        return $sorted->first();
    }

    protected function viewerHasPromotionAuthority(User $viewer): bool
    {
        return $this->access->isDirectorLike($viewer)
            || $viewer->hasAnyRole(['admiral', 'grand_admiral']);
    }

    protected function viewerCanPromoteTo(User $viewer, string $nextRank): bool
    {
        if ($this->access->isDirectorLike($viewer)) {
            return in_array($nextRank, (array) config('promotions.authorities.director', []), true);
        }

        if ($viewer->hasRole('grand_admiral')) {
            return in_array($nextRank, (array) config('promotions.authorities.grand_admiral', []), true);
        }

        if ($viewer->hasRole('admiral')) {
            return in_array($nextRank, (array) config('promotions.authorities.admiral', []), true);
        }

        return false;
    }

    protected function canCancelOffer(User $viewer, PromotionOffer $offer): bool
    {
        return (int) $viewer->id === (int) $offer->promoter_id
            || $this->access->isDirectorLike($viewer)
            || $viewer->hasRole('grand_admiral');
    }

    protected function canDemoteMember(User $viewer, User $member): bool
    {
        if ($viewer->is($member)) {
            return false;
        }

        $currentRank = $this->resolveControlledRank($member);

        return $currentRank !== null
            && $currentRank !== 'member'
            && ($this->access->isDirectorLike($viewer) || $viewer->hasRole('grand_admiral'));
    }

    protected function configurationReadinessForOffer(User $member, string $nextRank): array
    {
        if (! in_array($nextRank, (array) config('promotions.offerable_targets', []), true)) {
            return [
                'ready' => false,
                'reason' => "Promotion to {$this->rankLabel($nextRank)} is not currently configured for automated delivery.",
            ];
        }

        if (! $member->discord_id) {
            return [
                'ready' => false,
                'reason' => 'This member does not have a linked Discord account.',
            ];
        }

        if (! $this->discordRankRoleId($nextRank)) {
            return [
                'ready' => false,
                'reason' => "The Discord rank role for {$this->rankLabel($nextRank)} is not configured yet.",
            ];
        }

        if (trim((string) config("promotions.dm_templates.{$nextRank}", '')) === '') {
            return [
                'ready' => false,
                'reason' => "The DM template for {$this->rankLabel($nextRank)} is not configured yet.",
            ];
        }

        if (! $this->quarterChannelId($nextRank)) {
            return [
                'ready' => false,
                'reason' => "The quarter channel for {$this->rankLabel($nextRank)} is not configured yet.",
            ];
        }

        if (trim((string) config("promotions.quarter_templates.{$nextRank}", '')) === '') {
            return [
                'ready' => false,
                'reason' => "The quarter announcement template for {$this->rankLabel($nextRank)} is not configured yet.",
            ];
        }

        if (! count($this->branchOptionsForRank($nextRank))) {
            return [
                'ready' => false,
                'reason' => "No Discord branch roles are configured for {$this->rankLabel($nextRank)}.",
            ];
        }

        return [
            'ready' => true,
            'reason' => null,
        ];
    }

    protected function firstUnavailableReason(User $member, User $viewer, string $nextRank): ?string
    {
        if (! $this->viewerCanPromoteTo($viewer, $nextRank)) {
            return 'You do not have authority to issue that next-rank promotion.';
        }

        return $this->configurationReadinessForOffer($member, $nextRank)['reason'];
    }

    protected function branchOptionsForRank(string $rank): array
    {
        return collect(config("promotions.discord.branch_roles.{$rank}", []))
            ->filter(fn ($option) => filled(Arr::get($option, 'id')))
            ->map(fn ($option) => [
                'id' => (string) Arr::get($option, 'id'),
                'label' => (string) Arr::get($option, 'label', 'Branch'),
            ])
            ->values()
            ->all();
    }

    protected function branchLabelForRole(string $rank, ?string $branchRoleId): ?string
    {
        if (! $branchRoleId) {
            return null;
        }

        $match = collect($this->branchOptionsForRank($rank))
            ->firstWhere('id', $branchRoleId);

        return $match['label'] ?? null;
    }

    protected function discordRankRoleId(string $rank): ?string
    {
        $value = config("promotions.discord.rank_role_ids.{$rank}");

        return filled($value) ? (string) $value : null;
    }

    protected function quarterChannelId(string $rank): ?string
    {
        $value = config("promotions.discord.quarter_channel_ids.{$rank}");

        return filled($value) ? (string) $value : null;
    }

    protected function discordRoleIdsToRemoveForAcceptance(string $targetRank, string $selectedBranchRoleId): array
    {
        $rankRoleIds = collect(config('promotions.discord.rank_role_ids', []))
            ->except([$targetRank])
            ->values();

        $branchRoleIds = collect(config('promotions.discord.branch_roles', []))
            ->flatten(1)
            ->pluck('id')
            ->filter(fn ($id) => filled($id) && (string) $id !== (string) $selectedBranchRoleId);

        return $rankRoleIds
            ->merge($branchRoleIds)
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function renderTemplate(string $template, array $replacements): string
    {
        $rendered = $template;

        foreach ($replacements as $key => $value) {
            $rendered = str_replace(':' . $key, (string) $value, $rendered);
        }

        return trim($rendered);
    }

    protected function syncApplicationRank(User $member, string $targetRank): void
    {
        $memberRoleId = Role::query()->where('slug', 'member')->value('id');
        $targetRoleId = Role::query()->where('slug', $targetRank)->value('id');

        if (! $memberRoleId) {
            throw new RuntimeException('Application role "member" is missing from the database.');
        }

        if ($targetRank !== 'member' && ! $targetRoleId) {
            throw new RuntimeException("Application role \"{$targetRank}\" is missing from the database.");
        }

        $rankRoleSlugs = collect(config('promotions.ladder', []))
            ->filter(fn ($slug) => $slug !== 'member' && $slug !== $targetRank)
            ->values()
            ->all();
        $detachIds = Role::query()->whereIn('slug', $rankRoleSlugs)->pluck('id')->all();

        if ($detachIds) {
            $member->roles()->detach($detachIds);
        }

        $attachIds = array_values(array_filter(array_unique([
            $memberRoleId,
            $targetRoleId,
        ])));

        if ($attachIds) {
            $member->roles()->syncWithoutDetaching($attachIds);
        }

        $member->setRank($targetRank);
        $member->load('roles');
    }

    protected function expireIfStale(PromotionOffer $offer): PromotionOffer
    {
        if ($offer->state === PromotionOffer::STATE_PENDING && $offer->expires_at && $offer->expires_at->isPast()) {
            return $this->expireOffer($offer);
        }

        return $offer;
    }

    protected function expireOffer(PromotionOffer $offer): PromotionOffer
    {
        if ($offer->state !== PromotionOffer::STATE_PENDING) {
            return $offer;
        }

        return DB::transaction(function () use ($offer) {
            $offer->forceFill([
                'state' => PromotionOffer::STATE_EXPIRED,
                'expired_at' => now(),
            ])->save();

            $this->writeAuditLog('promotion_offer.expired', null, [
                'offer_id' => $offer->id,
                'member_id' => $offer->member_id,
                'to_rank' => $offer->to_rank,
            ]);

            event(new PromotionOfferExpired($offer));

            return $offer->fresh(['member', 'promoter']);
        });
    }

    protected function writeAuditLog(string $action, ?int $userId, array $meta): void
    {
        AuthAuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'meta' => $meta,
        ]);
    }

    protected function throwPromotionError(string $message): never
    {
        throw ValidationException::withMessages([
            'promotion' => $message,
        ]);
    }

    protected function promotionStorageReady(): bool
    {
        return Schema::hasTable('promotion_offers');
    }
}
