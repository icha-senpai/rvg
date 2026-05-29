<?php

namespace App\Domain\AccessControl\Listeners;

use App\Domain\AccessControl\Events\RoleAssigned;
use App\Domain\AccessControl\Events\RoleRevoked;
use Illuminate\Support\Facades\Cache;

class FlushUserAccessCache
{
    public function handle(RoleAssigned|RoleRevoked $event): void
    {
        Cache::forget("user_roles_{$event->user->id}");
        Cache::forget("user_permissions_{$event->user->id}");
    }
}
