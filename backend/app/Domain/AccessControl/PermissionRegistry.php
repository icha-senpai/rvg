<?php

namespace App\Domain\AccessControl;

class PermissionRegistry
{
    /**
     * Groups are logical sets of permission slugs.
     * This mirrors your seeder.
     */
    public const GROUPS = [
        'squadron' => [
            'squadron.view',
            'squadron.manage',
            'squadron.members.manage',
        ],

        'operation.viewing' => [
            'operation.view',
        ],

        'operation.hosting' => [
            'operation.create',
            'operation.host.small',
            'operation.host.medium',
            'operation.host.large',
            'operation.host.org',
        ],

        'operation.management' => [
            'operation.manage',
            'operation.members.manage',
        ],

        'user' => [
            'user.view',
            'user.manage',
        ],

        'system' => [
            'system.manage_roles',
            'system.manage_permissions',
            'system.settings',
        ],

        'domain' => [
            'domain.manage.resources',
            'domain.manage.operations',
        ],

        'analytics' => [
            'analytics.view',
            'analytics.operation',
        ],
    ];

    public static function group(string $group): array
    {
        return self::GROUPS[$group] ?? [];
    }

    public static function all(): array
    {
        return array_values(
            array_unique(
                array_merge(...array_values(self::GROUPS))
            )
        );
    }

    /**
     * Very simple wildcard expansion:
     * "operation.host.*" -> all host perms.
     */
    public static function expand(string $pattern): array
    {
        if ($pattern === '*') {
            return self::all();
        }

        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -2);

            return array_values(
                array_filter(self::all(), fn ($slug) => str_starts_with($slug, $prefix . '.'))
            );
        }

        return [$pattern];
    }
}
