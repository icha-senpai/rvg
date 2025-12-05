<?php

namespace App\Policies;

use App\Models\User;

class AdminPolicy
{
    public function access(User $user): bool
    {
        return $user->hasRole('director') || $user->hasRole('tech_director');
    }
}
