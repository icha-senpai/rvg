<?php

namespace App\Domain\Squadrons\Presenters;

use App\Models\SquadronMember;

class SquadronMemberPresenter
{
    public static function make(?SquadronMember $member): ?array
    {
        if (!$member) {
            return null;
        }

        return [
            'id'                => $member->id,
            'user_id'           => $member->user_id,
            'squadron_id'       => $member->squadron_id,

            // Membership state
            'status'            => $member->membership_status,
            'role'              => $member->role ?? 'member',
            'is_leader'         => $member->isLeader(),
            'is_lieutenant'     => $member->isLieutenant(),
            'is_member'         => $member->isMember(),

            // Timers
            'joined_at'         => optional($member->joined_at)->toIso8601String(),
            'left_at'           => optional($member->left_at)->toIso8601String(),
            'removed_at'        => optional($member->removed_at)->toIso8601String(),

            // User info (lightweight profile)
            'user' => $member->relationLoaded('user')
                ? [
                    'id'            => $member->user->id,
                    'name'          => $member->user->name,
                    'display_name'  => $member->user->display_name ?? $member->user->name,
                    'rank'          => $member->user->rank,
                    'rank_level'    => $member->user->rank_level,
                    'avatar'        => $member->user->discord_avatar,
                ]
                : null,
        ];
    }

    public static function collection($members): array
    {
        return $members->map(fn ($m) => self::make($m))->all();
    }
}
