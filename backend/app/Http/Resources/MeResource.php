<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'email'               => $this->email,

            // Rank info
            'rank'                => $this->rank,
            'rank_level'          => $this->rank_level,
            'rank_name'           => $this->rank_name,

            // Discord
            'discord_id'          => $this->discord_id,
            'discord_name'        => $this->discord_name,
            'discord_avatar'      => $this->discord_avatar,

            // RSI
            'rsi_handle'          => $this->rsi_handle,
            'rsi_verified_at'     => $this->rsi_verified_at,
            'rsi_verified'        => !is_null($this->rsi_verified_at), // <-- The magic flag

            // User-editable profile fields
            'bio'                 => $this->bio,
            'timezone'            => $this->timezone,

            'favorite_ships'       => $this->favorite_ships,
            'favorite_guns'        => $this->favorite_guns,
            'primary_role'         => $this->primary_role,
            'secondary_role'       => $this->secondary_role,
            'experience_ratings'   => $this->experience_ratings,
            'preferred_gameplay_style' => $this->preferred_gameplay_style,
            'callsign'             => $this->callsign,
            'typical_op_commitment' => $this->typical_op_commitment,
            'preferred_roles'     => $this->preferred_roles,
            'notification_settings' => $this->notification_settings,
            'availability_status' => $this->availability_status,
            'loa_note'            => $this->loa_note,
            'personal_tags'       => $this->personal_tags,

            'operations_joined_count' => $this->operations_joined_count,
            'operations_left_early_count' => $this->operations_left_early_count,
            'operations_completed_count' => $this->operations_completed_count,

            // RBAC roles
            'roles' => $this->roles->map(fn ($role) => [
                'slug' => $role->slug,
                'name' => $role->name,
            ])->values(),
        ];
    }
}
