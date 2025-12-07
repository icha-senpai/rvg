<?php

namespace App\Policies;

use App\Domain\AccessControl\AccessService;
use App\Models\User;


class AdminPolicy
{
    public function __construct(
        protected AccessService $access
    ) {}

    public function access(User $user): bool
    {
        return $this->access->canAccessAdminPanel($user);
    }
}
