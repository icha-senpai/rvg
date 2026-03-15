<?php

namespace App\Policies;

use App\Domain\AccessControl\AccessService;
use App\Models\OperationTemplate;
use App\Models\User;

class OperationTemplatePolicy
{
    public function __construct(
        protected AccessService $access
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->canViewAnyOperationTemplate($user);
    }

    public function view(User $user, OperationTemplate $template): bool
    {
        return $this->access->canViewOperationTemplate($user, $template);
    }

    public function create(User $user, ?string $scope = null, ?int $squadronId = null): bool
    {
        return $this->access->canCreateOperationTemplate($user, $scope, $squadronId);
    }

    public function update(User $user, OperationTemplate $template): bool
    {
        return $this->access->canUpdateOperationTemplate($user, $template);
    }

    public function delete(User $user, OperationTemplate $template): bool
    {
        return $this->access->canDeleteOperationTemplate($user, $template);
    }
}
