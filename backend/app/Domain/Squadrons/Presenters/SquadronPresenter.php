<?php

namespace App\Domain\Squadrons\Presenters;

use App\Models\Squadron;
use App\Domain\Media\Presenters\MediaPresenter;

/**
 * Shapes squadron models into the payloads used by squadron web pages and API
 * responses.
 */
class SquadronPresenter
{
    /**
     * Build the standard squadron payload.
     */
    public static function make(Squadron $squadron): array
    {
        $emblemUrl = null;

        // Prefer the related media record when it is loaded, but fall back to the
        // legacy stored file path so older squadron records still render cleanly.
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

            // Expose the raw state plus boolean helpers so the frontend does not
            // have to duplicate model-state comparisons.
            'status'        => $squadron->status,
            'is_active'     => $squadron->isActive(),
            'is_inactive'   => $squadron->isInactive(),
            'is_disbanded'  => $squadron->isDisbanded(),

            // Presentation fields are grouped here because they are commonly used
            // together when rendering squadron cards and headers.
            'motto'         => $squadron->motto,
            'description'   => $squadron->description,
            'primary_color' => $squadron->primary_color,
            'secondary_color' => $squadron->secondary_color,
            'emblem_url'    => $emblemUrl,
            'emblem'        => $squadron->relationLoaded('emblem') && $squadron->emblem
                ? MediaPresenter::make($squadron->emblem)->embedded()
                : null,

            // These flags drive common UI decisions around joining and recruiting.
            'recruiting'    => (bool) $squadron->recruiting,
            'recruitment_propaganda' => $squadron->recruitment_propaganda,

            // Keep the leader payload intentionally lightweight because list and
            // detail views only need a small public-facing profile.
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

            // Members are only presented when the relation is already loaded so the
            // presenter does not accidentally turn a list call into an N+1 fetch.
            'members' => $squadron->relationLoaded('members')
                ? SquadronMemberPresenter::collection($squadron->members)
                : null,

            // Member count mirrors the loaded members collection when available.
            'member_count' => $squadron->relationLoaded('members')
                ? $squadron->members->count()
                : null,
        ];
    }

    /**
     * Build the standard payload for a collection of squadrons.
     */
    public static function collection($squadrons): array
    {
        return $squadrons->map(fn ($sq) => self::make($sq))->all();
    }
}
