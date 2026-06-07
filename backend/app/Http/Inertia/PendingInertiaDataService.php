<?php

namespace App\Http\Inertia;

use App\Domain\AccessControl\AccessService;
use App\Models\SquadronMember;
use App\Models\User;
use App\Services\LedgerFeatureService;
use App\Services\LedgerTransferService;

class PendingInertiaDataService
{
    public function __construct(
        protected AccessService $access,
        protected LedgerFeatureService $ledgerFeature,
        protected LedgerTransferService $ledgerTransfers,
    ) {}

    public function transferBadgesFor(?User $user): array
    {
        $badges = [
            'personal' => 0,
            'squadron' => 0,
            'organization' => 0,
        ];

        if (! $user || ! $this->ledgerFeature->canAccess($user)) {
            return $badges;
        }

        $contexts = [
            'personal' => $this->ledgerTransfers->personalInboxContext($user),
        ];

        $activeSquadron = $this->activeSquadronFor($user);

        if ($activeSquadron) {
            $contexts['squadron'] = $this->ledgerTransfers->squadronInboxContext($user, $activeSquadron);
        }

        if ($user->can('manage-org-ledger')) {
            $contexts['organization'] = $this->ledgerTransfers->organizationInboxContext($user);
        }

        return array_merge($badges, $this->ledgerTransfers->pendingTransferCountsForContexts($user, $contexts));
    }

    public function pendingSquadronApplicationsFor(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        $activeSquadron = $this->activeSquadronFor($user);

        if (! $activeSquadron || ! $this->access->canManageSquadronMembers($user, $activeSquadron)) {
            return 0;
        }

        return SquadronMember::query()
            ->where('squadron_id', $activeSquadron->id)
            ->where('membership_status', SquadronMember::STATUS_PENDING)
            ->count();
    }

    protected function activeSquadronFor(User $user)
    {
        return $user->squadrons?->first(fn ($squadron) => $squadron?->pivot?->membership_status === 'active')
            ?? $user->squadrons?->first();
    }
}
