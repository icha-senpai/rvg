<?php

namespace App\Domain\AccessControl\Events;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public Role $role
    ) {}
}
