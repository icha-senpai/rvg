<?php

namespace App\Domain\AccessControl;

use App\Models\User;

class RoleHierarchy
{
    /**
     * Higher number = higher authority.
     */
    public const LEVELS = [
        'member'            => 1,
        'lieutenant'        => 2,
        'cit'               => 3,
        'commander'         => 4,
        'wing_commander'    => 5,
        'admiral'           => 6,
        'grand_admiral'     => 7,
        'tech_director'     => 8,
        'director'          => 9,
    ];

    public static function levelFor(string $roleSlug): int
    {
        return self::LEVELS[$roleSlug] ?? 0;
    }

    /**
     * Highest level among all the user's roles.
     */
    public static function userLevel(User $user): int
    {
        $roles = $user->roles ?? collect();

        return $roles
            ->map(fn ($r) => self::levelFor($r->slug))
            ->max() ?? 0;
    }

    /**
     * Check if user is at least the given role level.
     */
    public static function userAtLeast(User $user, string $roleSlug): bool
    {
        $required = self::levelFor($roleSlug);
        $actual   = self::userLevel($user);

        return $actual >= $required;
    }
}
