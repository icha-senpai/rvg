<?php

namespace App\Domain\AccessControl;

use App\Models\Operation;
use App\Models\Squadron;
use App\Models\User;

class OperationAccessService
{
    public function __construct(
        protected SharedAccessService $shared
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

        if ($operation->squadron_id) {
            return $this->shared->squadronMembershipForId($user, (int) $operation->squadron_id) !== null;
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
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        if (! $operation->squadron_id) {
            return (int) $operation->created_by === (int) $user->id
                && $this->shared->atLeast($user, 'lieutenant');
        }

        $squadron = Squadron::find($operation->squadron_id);

        if (! $squadron) {
            return false;
        }

        if ((int) $operation->created_by === (int) $user->id
            && $this->canCreateOperation($user, $squadron)
        ) {
            return true;
        }

        if ($this->shared->isSquadronLeader($user, $squadron)) {
            return true;
        }

        if ($this->shared->isSquadronLieutenant($user, $squadron)) {
            return true;
        }

        return false;
    }

    public function canDeleteOperation(User $user, Operation $operation): bool
    {
        return $this->canUpdateOperation($user, $operation);
    }

    public function canManageOperationMembers(User $user, Operation $operation): bool
    {
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        if ($this->shared->can($user, 'operation.members.manage')) {
            return true;
        }

        if ($operation->squadron_id) {
            $squadron = Squadron::find($operation->squadron_id);

            if ($squadron && $this->shared->isSquadronLeader($user, $squadron)) {
                return true;
            }
        }

        return false;
    }

    public function canAdjustOperationStats(User $user, Operation $operation): bool
    {
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        if ($this->shared->can($user, 'analytics.operation')
            || $this->shared->can($user, 'operation.stats.manage')) {
            return true;
        }

        return false;
    }

    public function canManageOperation(User $user, Operation $operation): bool
    {
        return $this->canUpdateOperation($user, $operation)
            || $this->canManageOperationMembers($user, $operation)
            || $this->canAdjustOperationStats($user, $operation);
    }
}
