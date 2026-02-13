<?php

namespace App\Domain\Squadrons\Presenters;

use App\Models\Squadron;
use App\Domain\Media\Presenters\MediaPresenter;

class SquadronPresenter
{
    public static function make(Squadron $squadron): array
    {
        $emblemUrl = null;

        if ($squadron->relationLoaded('emblem') && $squadron->emblem) {
            $emblemUrl = $squadron->emblem->display_url;
        } elseif ($squadron->emblem_path) {
            $emblemUrl = asset('storage/' . $squadron->emblem_path);
        }

        return [
            'id'            => $squadron->id,
            'name'          => $squadron->name,
            'slug'          => $squadron->slug,

            'branch'        => $squadron->branch,
            'division'      => $squadron->division,

            // State
            'status'        => $squadron->status,
            'is_active'     => $squadron->isActive(),
            'is_inactive'   => $squadron->isInactive(),
            'is_disbanded'  => $squadron->isDisbanded(),

            // Aesthetics
            'motto'         => $squadron->motto,
            'description'   => $squadron->description,
            'primary_color' => $squadron->primary_color,
            'secondary_color' => $squadron->secondary_color,
            'emblem_url'    => $emblemUrl,
            'emblem'        => $squadron->relationLoaded('emblem') && $squadron->emblem
                ? MediaPresenter::make($squadron->emblem)->embedded()
                : null,

            // Flags
            'recruiting'    => (bool) $squadron->recruiting,
            'recruitment_propaganda' => $squadron->recruitment_propaganda,

            // Leader (light profile)
            'leader' => $squadron->relationLoaded('leader') && $squadron->leader
                ? [
                    'id'          => $squadron->leader->id,
                    'name'        => $squadron->leader->name,
                    'display_name'=> $squadron->leader->display_name ?? $squadron->leader->name,
                    'rsi_handle'  => $squadron->leader->rsi_handle,
                    'rank'        => $squadron->leader->rank,
                    'rank_level'  => $squadron->leader->rank_level,
                    'avatar'      => $squadron->leader->discord_avatar,
                    'roles'       => $squadron->leader->relationLoaded('roles')
                        ? $squadron->leader->roles->map(fn ($role) => [
                            'slug' => $role->slug,
                            'name' => $role->name,
                        ])->values()
                        : [],
                ]
                : null,

            // Members (if already loaded)
            'members' => $squadron->relationLoaded('members')
                ? SquadronMemberPresenter::collection($squadron->members)
                : null,

            // Counts
            'member_count' => $squadron->relationLoaded('members')
                ? $squadron->members->count()
                : null,
        ];
    }

    public static function collection($squadrons): array
    {
        return $squadrons->map(fn ($sq) => self::make($sq))->all();
    }
}
