<?php

namespace App\Domain\AccessControl;

use App\Models\OperationTemplate;
use App\Models\Squadron;
use App\Models\User;

class OperationTemplateAccessService
{
    public function __construct(
        protected SharedAccessService $shared
    ) {}

    public function isDirectorLike(User $user): bool
    {
        return $this->shared->isDirectorLike($user);
    }

    public function canViewAnyOperationTemplate(User $user): bool
    {
        return $this->isDirectorLike($user)
            || $this->shared->isOfficer($user);
    }

    public function visibleOperationTemplateSquadronIds(User $user): array
    {
        if ($this->isDirectorLike($user)) {
            return [];
        }

        return $user->squadronMemberships()
            ->active()
            ->pluck('squadron_id')
            ->values()
            ->all();
    }

    public function canViewOperationTemplate(User $user, OperationTemplate $template): bool
    {
        if (! $this->canViewAnyOperationTemplate($user)) {
            return false;
        }

        if ($this->isDirectorLike($user)) {
            return true;
        }

        return match ($template->scope) {
            OperationTemplate::SCOPE_GLOBAL => true,
            OperationTemplate::SCOPE_PERSONAL => (int) $template->owner_user_id === (int) $user->id,
            OperationTemplate::SCOPE_SQUADRON => $template->squadron_id
                ? $this->shared->squadronMembershipForId($user, (int) $template->squadron_id) !== null
                : false,
            default => false,
        };
    }

    public function canCreateOperationTemplate(User $user, ?string $scope = null, ?int $squadronId = null): bool
    {
        if (! $this->canViewAnyOperationTemplate($user)) {
            return false;
        }

        if ($scope === null) {
            return true;
        }

        if ($this->isDirectorLike($user)) {
            return true;
        }

        return match ($scope) {
            OperationTemplate::SCOPE_GLOBAL => false,
            OperationTemplate::SCOPE_PERSONAL => true,
            OperationTemplate::SCOPE_SQUADRON => $squadronId
                ? $this->shared->squadronMembershipForId($user, $squadronId) !== null
                : false,
            default => false,
        };
    }

    public function canUpdateOperationTemplate(User $user, OperationTemplate $template): bool
    {
        if ($this->isDirectorLike($user)) {
            return true;
        }

        if (! $this->canViewAnyOperationTemplate($user)) {
            return false;
        }

        return match ($template->scope) {
            OperationTemplate::SCOPE_GLOBAL => false,
            OperationTemplate::SCOPE_PERSONAL => (int) $template->owner_user_id === (int) $user->id,
            OperationTemplate::SCOPE_SQUADRON => $template->squadron_id
                ? $this->canUpdateSquadronTemplate($user, (int) $template->squadron_id, (int) $template->created_by)
                : false,
            default => false,
        };
    }

    public function canDeleteOperationTemplate(User $user, OperationTemplate $template): bool
    {
        return $this->canUpdateOperationTemplate($user, $template);
    }

    protected function canUpdateSquadronTemplate(User $user, int $squadronId, int $createdBy): bool
    {
        $squadron = Squadron::find($squadronId);

        if (! $squadron) {
            return false;
        }

        if ($createdBy === (int) $user->id) {
            return true;
        }

        return $this->shared->isSquadronLeader($user, $squadron)
            || $this->shared->isSquadronLieutenant($user, $squadron);
    }
}
