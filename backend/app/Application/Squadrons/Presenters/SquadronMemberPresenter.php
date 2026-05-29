<?php

namespace App\Application\Squadrons\Presenters;

use App\Models\SquadronMember;

/**
 * Shapes squadron membership records into lightweight payloads for web and API
 * consumers.
 */
class SquadronMemberPresenter
{
    public static function make(?SquadronMember $member): ?array
    {
        if (! $member) {
            return null;
        }

        return [
            'id' => $member->id,
            'user_id' => $member->user_id,
            'squadron_id' => $member->squadron_id,
            'membership_status' => $member->membership_status,
            'role' => $member->role ?? 'member',
            'is_leader' => $member->isLeader(),
            'is_lieutenant' => $member->isLieutenant(),
            'is_member' => $member->isMember(),
            'joined_at' => optional($member->joined_at)->toIso8601String(),
            'left_at' => optional($member->left_at)->toIso8601String(),
            'removed_at' => optional($member->removed_at)->toIso8601String(),
            'user' => $member->relationLoaded('user')
                ? [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'display_name' => $member->user->display_name ?? $member->user->name,
                    'rsi_handle' => $member->user->rsi_handle,
                    'rank' => $member->user->rank,
                    'rank_level' => $member->user->rank_level,
                    'avatar' => $member->user->discord_avatar,
                    'roles' => $member->user->relationLoaded('roles')
                        ? $member->user->roles->map(fn ($role) => [
                            'slug' => $role->slug,
                            'name' => $role->name,
                        ])->values()
                        : [],
                ]
                : null,
        ];
    }

    public static function collection($members): array
    {
        return $members->map(fn ($member) => self::make($member))->all();
    }
}
