<?php

namespace App\Domain\Squadrons\Presenters;

use App\Models\SquadronMember;

/**
 * Shapes squadron membership records into lightweight payloads for web and API
 * consumers.
 */
class SquadronMemberPresenter
{
    /**
     * Build the standard payload for one squadron membership record.
     */
    public static function make(?SquadronMember $member): ?array
    {
        if (! $member) {
            return null;
        }

        return [
            'id'                => $member->id,
            'user_id'           => $member->user_id,
            'squadron_id'       => $member->squadron_id,

            // Expose both the stored membership state and the convenience boolean
            // helpers used by the frontend for badges and actions.
            'membership_status' => $member->membership_status,
            'role'              => $member->role ?? 'member',
            'is_leader'         => $member->isLeader(),
            'is_lieutenant'     => $member->isLieutenant(),
            'is_member'         => $member->isMember(),

            // These timestamps let the UI explain when the relationship started or
            // ended without needing date logic in Vue.
            'joined_at'         => optional($member->joined_at)->toIso8601String(),
            'left_at'           => optional($member->left_at)->toIso8601String(),
            'removed_at'        => optional($member->removed_at)->toIso8601String(),

            // Keep the related user profile lightweight because member lists can be
            // large and do not need full user detail.
            'user' => $member->relationLoaded('user')
                ? [
                    'id'            => $member->user->id,
                    'name'          => $member->user->name,
                    'display_name'  => $member->user->display_name ?? $member->user->name,
                    'rsi_handle'    => $member->user->rsi_handle,
                    'rank'          => $member->user->rank,
                    'rank_level'    => $member->user->rank_level,
                    'avatar'        => $member->user->discord_avatar,
                    'roles'         => $member->user->relationLoaded('roles')
                        ? $member->user->roles->map(fn ($role) => [
                            'slug' => $role->slug,
                            'name' => $role->name,
                        ])->values()
                        : [],
                ]
                : null,
        ];
    }

    /**
     * Build the standard payload for a collection of membership records.
     */
    public static function collection($members): array
    {
        return $members->map(fn ($m) => self::make($m))->all();
    }
}
