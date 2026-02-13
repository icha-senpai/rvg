<?php

namespace App\Policies;

use App\Domain\AccessControl\RoleHierarchy;
use App\Models\OperationTemplate;
use App\Models\Squadron;
use App\Models\User;

class OperationTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isRankTwoOrDirectorLike($user);
    }

    public function view(User $user, OperationTemplate $template): bool
    {
        if (! $this->isRankTwoOrDirectorLike($user)) {
            return false;
        }

        if ($this->isDirectorLike($user)) {
            return true;
        }

        if ($template->scope === OperationTemplate::SCOPE_GLOBAL) {
            return true;
        }

        if ($template->scope === OperationTemplate::SCOPE_PERSONAL) {
            return (int) $template->owner_user_id === (int) $user->id;
        }

        if ($template->scope === OperationTemplate::SCOPE_SQUADRON) {
            if (! $template->squadron_id) {
                return false;
            }

            $squadron = Squadron::find($template->squadron_id);
            if (! $squadron) {
                return false;
            }

            $membership = $user->squadronMembershipFor($squadron);
            if (! $membership || ! $membership->isActive()) {
                return false;
            }

            return $this->isRankTwoOrDirectorLike($user);
        }

        return false;
    }

    public function create(User $user, ?string $scope = null, ?int $squadronId = null): bool
    {
        if (! $this->isRankTwoOrDirectorLike($user)) {
            return false;
        }

        if ($scope === null) {
            return true;
        }

        if ($this->isDirectorLike($user)) {
            return true;
        }

        if ($scope === OperationTemplate::SCOPE_GLOBAL) {
            return false;
        }

        if ($scope === OperationTemplate::SCOPE_PERSONAL) {
            return true;
        }

        if ($scope === OperationTemplate::SCOPE_SQUADRON) {
            if (! $squadronId) {
                return false;
            }

            $squadron = Squadron::find($squadronId);
            if (! $squadron) {
                return false;
            }

            $membership = $user->squadronMembershipFor($squadron);
            if (! $membership || ! $membership->isActive()) {
                return false;
            }

            return $this->isRankTwoOrDirectorLike($user);
        }

        return false;
    }

    public function update(User $user, OperationTemplate $template): bool
    {
        if ($this->isDirectorLike($user)) {
            return true;
        }

        if (! $this->isRankTwoOrDirectorLike($user)) {
            return false;
        }

        if ($template->scope === OperationTemplate::SCOPE_GLOBAL) {
            return false;
        }

        if ($template->scope === OperationTemplate::SCOPE_PERSONAL) {
            return (int) $template->owner_user_id === (int) $user->id;
        }

        if ($template->scope === OperationTemplate::SCOPE_SQUADRON) {
            if (! $template->squadron_id) {
                return false;
            }

            $squadron = Squadron::find($template->squadron_id);
            if (! $squadron) {
                return false;
            }

            if ((int) $template->created_by === (int) $user->id) {
                return true;
            }

            return $user->isSquadronLeader($squadron)
                || $user->isSquadronLieutenant($squadron);
        }

        return false;
    }

    public function delete(User $user, OperationTemplate $template): bool
    {
        return $this->update($user, $template);
    }

    protected function isDirectorLike(User $user): bool
    {
        return $user->hasRole('director') || $user->hasRole('tech_director');
    }

    protected function isRankTwoOrDirectorLike(User $user): bool
    {
        $user->loadMissing('roles:id,slug');

        return $this->isDirectorLike($user)
            || RoleHierarchy::userAtLeast($user, 'lieutenant');
    }
}
