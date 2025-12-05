<?php

namespace App\Domain\Squadrons\Presenters;

use App\Models\Squadron;

class SquadronPresenter
{
    public static function make(Squadron $squadron): array
    {
        return [
            'id'            => $squadron->id,
            'name'          => $squadron->name,
            'slug'          => $squadron->slug,

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
            'emblem_url'    => $squadron->emblem_path
                                ? asset('storage/' . $squadron->emblem_path)
                                : null,

            // Flags
            'recruiting'    => (bool) $squadron->recruiting,

            // Leader (light profile)
            'leader' => $squadron->relationLoaded('leader') && $squadron->leader
                ? [
                    'id'          => $squadron->leader->id,
                    'name'        => $squadron->leader->name,
                    'display_name'=> $squadron->leader->display_name ?? $squadron->leader->name,
                    'rank'        => $squadron->leader->rank,
                    'rank_level'  => $squadron->leader->rank_level,
                    'avatar'      => $squadron->leader->discord_avatar,
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
