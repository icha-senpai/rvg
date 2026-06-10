<?php

namespace App\Services;

use App\Domain\AccessControl\AccessService;
use App\Models\LedgerTransferRequest;
use App\Models\Squadron;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LedgerTransferContextService
{
    public function __construct(
        protected LedgerCycleService $cycles,
        protected AccessService $access,
    ) {}

    public function personalInboxContext(User $actor): array
    {
        return $this->transferContextForPersonal($actor);
    }

    public function squadronInboxContext(User $actor, Squadron $squadron): array
    {
        return [
            'type' => 'squadron',
            'label' => "{$squadron->name} Squadron Assets & Funds",
            'account' => null,
            'user_id' => $actor->id,
            'subject_user' => $actor,
            'squadron_id' => $squadron->id,
            'squadron' => $squadron,
            'is_org_owned' => false,
        ];
    }

    public function organizationInboxContext(User $actor): array
    {
        return [
            'type' => 'organization',
            'label' => 'Horizon Treasury',
            'account' => null,
            'user_id' => $actor->id,
            'subject_user' => $actor,
            'squadron_id' => null,
            'squadron' => null,
            'is_org_owned' => true,
        ];
    }

    public function transferContextForPersonal(User $actor): array
    {
        $account = $this->cycles->defaultAccountFor($actor);

        return [
            'type' => 'personal',
            'label' => "{$this->transferDisplayName($actor)}'s Assets & Funds",
            'account' => $account,
            'user_id' => $actor->id,
            'subject_user' => $actor,
            'squadron_id' => null,
            'squadron' => null,
            'is_org_owned' => false,
        ];
    }

    public function transferContextForSquadron(User $actor, Squadron $squadron): array
    {
        if (! $this->access->canManageSquadronLedger($actor, $squadron)) {
            throw ValidationException::withMessages([
                'destination_type' => 'You do not have permission to move funds through that squadron ledger.',
            ]);
        }

        $account = $this->cycles->defaultSquadronAccountFor($squadron, $actor);

        return [
            'type' => 'squadron',
            'label' => "{$squadron->name} Squadron Assets & Funds",
            'account' => $account,
            'user_id' => $actor->id,
            'subject_user' => $actor,
            'squadron_id' => $squadron->id,
            'squadron' => $squadron,
            'is_org_owned' => false,
        ];
    }

    public function transferContextForOrganization(User $actor): array
    {
        if (! $this->canManageOrganizationLedger($actor)) {
            throw ValidationException::withMessages([
                'destination_type' => 'You do not have permission to move funds through Horizon Treasury.',
            ]);
        }

        $account = $this->cycles->defaultOrgAccountFor($actor);

        return [
            'type' => 'organization',
            'label' => 'Horizon Treasury',
            'account' => $account,
            'user_id' => $actor->id,
            'subject_user' => $actor,
            'squadron_id' => null,
            'squadron' => null,
            'is_org_owned' => true,
        ];
    }

    public function transferContextForRequestSource(LedgerTransferRequest $request): array
    {
        $attributedUser = $request->requestedBy
            ?? $request->sourceUser
            ?? User::query()->findOrFail($request->requested_by_user_id);

        if ($request->source_is_org_owned) {
            return $this->transferContextForOrganizationRecord($attributedUser);
        }

        if ($request->source_squadron_id) {
            $squadron = $request->sourceSquadron
                ?? Squadron::query()->findOrFail($request->source_squadron_id);

            return $this->transferContextForSquadronRecord($squadron, $request->sourceUser ?? $attributedUser);
        }

        $user = $request->sourceUser ?? User::query()->findOrFail($request->source_user_id);

        return $this->transferContextForPersonal($user);
    }

    public function transferContextForRequestDestination(LedgerTransferRequest $request): array
    {
        if ($request->destination_is_external) {
            return $this->transferContextForExternalDestination();
        }

        $attributedUser = $request->requestedBy
            ?? $request->sourceUser
            ?? User::query()->findOrFail($request->requested_by_user_id);

        if ($request->destination_is_org_owned) {
            return $this->transferContextForOrganizationRecord($attributedUser);
        }

        if ($request->destination_squadron_id) {
            $squadron = $request->destinationSquadron
                ?? Squadron::query()->findOrFail($request->destination_squadron_id);

            return $this->transferContextForSquadronRecord($squadron, $request->sourceUser ?? $attributedUser);
        }

        $user = $request->destinationUser ?? User::query()->findOrFail($request->destination_user_id);

        return $this->transferContextForPersonal($user);
    }

    public function resolveTransferDestination(User $actor, array $sourceContext, array $data): array
    {
        $destination = match ($data['destination_type']) {
            'personal' => $this->transferContextForRequestedPersonalRecipient((int) ($data['destination_user_id'] ?? 0)),
            'squadron' => $this->transferContextForRequestedSquadronDestination(
                $actor,
                (int) ($data['destination_squadron_id'] ?? 0)
            ),
            'organization' => $this->transferContextForOrganizationDestination($actor, $sourceContext),
            'external' => $this->transferContextForExternalDestination(),
            default => null,
        };

        if (! $destination) {
            throw ValidationException::withMessages([
                'destination_type' => 'Choose a valid transfer destination.',
            ]);
        }

        if ($this->transferContextsMatch($sourceContext, $destination)) {
            throw ValidationException::withMessages([
                'destination_type' => 'Pick a different destination for this transfer.',
            ]);
        }

        return $destination;
    }

    public function transferContextsMatch(array $sourceContext, array $destinationContext): bool
    {
        if ($sourceContext['type'] !== $destinationContext['type']) {
            return false;
        }

        return match ($sourceContext['type']) {
            'personal' => (int) ($sourceContext['user_id'] ?? 0) === (int) ($destinationContext['user_id'] ?? 0),
            'squadron' => (int) ($sourceContext['squadron_id'] ?? 0) === (int) ($destinationContext['squadron_id'] ?? 0),
            'organization' => true,
            'external' => true,
            default => false,
        };
    }

    public function transferDisplayName(User $user): string
    {
        return $user->rsi_handle
            ?? $user->discord_name
            ?? $user->name
            ?? "Member {$user->id}";
    }

    public function canActorDirectlySendIntoSquadron(User $actor, Squadron $squadron): bool
    {
        if ($squadron->members()->where('user_id', $actor->id)->where('membership_status', 'active')->exists()) {
            return true;
        }

        return $this->access->canManageSquadronLedger($actor, $squadron);
    }

    public function canManageOrganizationLedger(User $actor): bool
    {
        return $actor->can('manage-org-ledger');
    }

    protected function transferContextForRequestedPersonalRecipient(int $userId): array
    {
        if ($userId <= 0) {
            throw ValidationException::withMessages([
                'destination_user_id' => 'Choose which verified member should receive this transfer.',
            ]);
        }

        $user = User::query()
            ->whereKey($userId)
            ->where('global_status', User::STATUS_ACTIVE)
            ->whereNotNull('rsi_verified_at')
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'destination_user_id' => 'Choose a valid verified member destination.',
            ]);
        }

        return $this->transferContextForPersonal($user);
    }

    protected function transferContextForRequestedSquadronDestination(User $actor, int $squadronId): array
    {
        if ($squadronId <= 0) {
            throw ValidationException::withMessages([
                'destination_squadron_id' => 'Choose which squadron ledger should receive the funds.',
            ]);
        }

        $squadron = Squadron::query()
            ->whereKey($squadronId)
            ->where('status', 'active')
            ->first();

        if (! $squadron) {
            throw ValidationException::withMessages([
                'destination_squadron_id' => 'Choose a valid active squadron destination.',
            ]);
        }

        $account = $this->cycles->defaultSquadronAccountFor($squadron, $actor);

        return [
            'type' => 'squadron',
            'label' => "{$squadron->name} Squadron Assets & Funds",
            'account' => $account,
            'user_id' => $actor->id,
            'subject_user' => $actor,
            'squadron_id' => $squadron->id,
            'squadron' => $squadron,
            'is_org_owned' => false,
        ];
    }

    protected function transferContextForOrganizationDestination(User $actor, array $sourceContext): array
    {
        if (in_array($sourceContext['type'], ['personal', 'squadron'], true)) {
            $account = $this->cycles->defaultOrgAccountFor($actor);

            return [
                'type' => 'organization',
                'label' => 'Horizon Treasury',
                'account' => $account,
                'user_id' => $actor->id,
                'subject_user' => $actor,
                'squadron_id' => null,
                'squadron' => null,
                'is_org_owned' => true,
            ];
        }

        return $this->transferContextForOrganization($actor);
    }

    protected function transferContextForExternalDestination(): array
    {
        return [
            'type' => 'external',
            'label' => 'Outside Horizon',
            'account' => null,
            'user_id' => null,
            'subject_user' => null,
            'squadron_id' => null,
            'squadron' => null,
            'is_org_owned' => false,
            'is_external' => true,
        ];
    }

    protected function transferContextForSquadronRecord(Squadron $squadron, User $attributedUser): array
    {
        $account = $this->cycles->defaultSquadronAccountFor($squadron, $attributedUser);

        return [
            'type' => 'squadron',
            'label' => "{$squadron->name} Squadron Assets & Funds",
            'account' => $account,
            'user_id' => $attributedUser->id,
            'subject_user' => $attributedUser,
            'squadron_id' => $squadron->id,
            'squadron' => $squadron,
            'is_org_owned' => false,
        ];
    }

    protected function transferContextForOrganizationRecord(User $attributedUser): array
    {
        $account = $this->cycles->defaultOrgAccountFor($attributedUser);

        return [
            'type' => 'organization',
            'label' => 'Horizon Treasury',
            'account' => $account,
            'user_id' => $attributedUser->id,
            'subject_user' => $attributedUser,
            'squadron_id' => null,
            'squadron' => null,
            'is_org_owned' => true,
        ];
    }
}
