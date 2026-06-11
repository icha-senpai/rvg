<?php

namespace App\Domain\AccessControl;

use App\Models\Operation;
use App\Models\Squadron;
use App\Models\User;

class OperationAccessService
{
    public function __construct(
        protected SharedAccessService $shared,
        protected SquadronMembershipReadService $memberships
    ) {}

    public function canViewAnyOperation(User $user): bool
    {
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        return $this->shared->can($user, 'operation.view');
    }

    public function canViewOperation(User $user, Operation $operation): bool
    {
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        if ($this->shared->can($user, 'operation.view')) {
            return true;
        }

        if ($operation->visibility === 'open') {
            return true;
        }

        if ($operation->visibility === 'private') {
            return (int) $operation->created_by === (int) $user->id;
        }

        if ($operation->visibility === 'squadron') {
            return $this->matchesSquadronAudience($user, $operation);
        }

        return false;
    }

    public function canCreateOperation(User $user, ?Squadron $squadron = null): bool
    {
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        if (! $squadron) {
            return $this->shared->atLeast($user, 'lieutenant');
        }

        if ($this->shared->atLeast($user, 'lieutenant')) {
            return $this->shared->isSquadronMember($user, $squadron);
        }

        return false;
    }

    public function canUpdateOperation(User $user, Operation $operation): bool
    {
        return $this->canFullyManageOperation($user, $operation);
    }

    public function canDeleteOperation(User $user, Operation $operation): bool
    {
        return $this->canUpdateOperation($user, $operation);
    }

    public function canManageOperationMembers(User $user, Operation $operation): bool
    {
        return $this->canFullyManageOperation($user, $operation);
    }

    public function canViewOperationSlots(User $user, Operation $operation): bool
    {
        return $this->canFullyManageOperation($user, $operation);
    }

    public function canAssignOperationSlots(User $user, Operation $operation): bool
    {
        return $this->canViewOperationSlots($user, $operation);
    }

    public function canAdjustOperationStats(User $user, Operation $operation): bool
    {
        return $this->canFullyManageOperation($user, $operation);
    }

    public function canManageAfterActionReport(User $user, Operation $operation): bool
    {
        if (! $operation->isCompleted()) {
            return false;
        }

        return $this->canFullyManageOperation($user, $operation);
    }

    public function canManageOperation(User $user, Operation $operation): bool
    {
        return $this->canFullyManageOperation($user, $operation);
    }

    protected function canFullyManageOperation(User $user, Operation $operation): bool
    {
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        $squadron = $this->memberships->squadronForOperation($operation);

        if (! $squadron) {
            return (int) $operation->created_by === (int) $user->id;
        }

        return $this->isOwningSquadronCommand($user, $squadron);
    }

    protected function isOwningSquadronCommand(User $user, Squadron $squadron): bool
    {
        return $this->shared->isSquadronLeader($user, $squadron)
            || $this->shared->isSquadronLieutenant($user, $squadron)
            || (int) $squadron->leader_id === (int) $user->id;
    }

    protected function matchesSquadronAudience(User $user, Operation $operation): bool
    {
        if ($operation->squadron_id && $this->shared->squadronMembershipForId($user, (int) $operation->squadron_id) !== null) {
            return true;
        }

        $squadronNames = collect(explode(',', (string) ($operation->squadron_name ?? '')))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->values();

        if ($squadronNames->isEmpty()) {
            return false;
        }

        return $user->squadronMemberships()
            ->active()
            ->whereHas('squadron', function ($query) use ($squadronNames) {
                $query->whereIn('name', $squadronNames->all());
            })
            ->exists();
    }
}
