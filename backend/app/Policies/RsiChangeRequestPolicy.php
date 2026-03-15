<?php

namespace App\Policies;

use App\Domain\AccessControl\AccessService;
use App\Models\RsiChangeRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RsiChangeRequestPolicy
{
    public function __construct(
        protected AccessService $access
    ) {}

    public function viewAny(User $user)
    {
        return $this->access->can($user, 'user.manage');
    }

    public function approve(User $user, RsiChangeRequest $req)
    {
        return $this->access->can($user, 'user.manage');
    }

    public function reject(User $user, RsiChangeRequest $req)
    {
        return $this->access->can($user, 'user.manage');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RsiChangeRequest $rsiChangeRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RsiChangeRequest $rsiChangeRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RsiChangeRequest $rsiChangeRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RsiChangeRequest $rsiChangeRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RsiChangeRequest $rsiChangeRequest): bool
    {
        return false;
    }
}
