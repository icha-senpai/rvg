<?php

namespace App\Policies;

use App\Domain\AccessControl\AccessService;
use App\Models\Operation;
use App\Models\Squadron;
use App\Models\User;

class OperationPolicy
{
    public function __construct(
        protected AccessService $access
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->canViewAnyOperation($user);
    }

    public function view(User $user, Operation $operation): bool
    {
        return $this->access->canViewOperation($user, $operation);
    }

    public function create(User $user, ?Squadron $squadron = null): bool
    {
        return $this->access->canCreateOperation($user, $squadron);
    }

    public function update(User $user, Operation $operation): bool
    {
        return $this->access->canUpdateOperation($user, $operation);
    }

    public function delete(User $user, Operation $operation): bool
    {
        return $this->access->canDeleteOperation($user, $operation);
    }

    public function manageMembers(User $user, Operation $operation): bool
    {
        return $this->access->canManageOperationMembers($user, $operation);
    }

    public function viewSlots(User $user, Operation $operation): bool
    {
        return $this->access->canViewOperationSlots($user, $operation);
    }

    public function assignSlots(User $user, Operation $operation): bool
    {
        return $this->access->canAssignOperationSlots($user, $operation);
    }

    public function adjustStats(User $user, Operation $operation): bool
    {
        return $this->access->canAdjustOperationStats($user, $operation);
    }

    public function manage(User $user, Operation $operation): bool
    {
        return $this->access->canManageOperation($user, $operation);
    }
}
