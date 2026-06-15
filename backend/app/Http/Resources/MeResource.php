<?php

namespace App\Http\Resources;

use App\Domain\AccessControl\AccessService;
use App\Domain\Promotions\PromotionWorkflowService;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class MeResource extends JsonResource
{
    public function toArray($request)
    {
        $viewer = $request?->user();
        $access = app(AccessService::class);
        $promotions = app(PromotionWorkflowService::class);
        $isDirectorLike = (bool) ($viewer && $access->isDirectorLike($viewer));

        $canViewRestrictedOperationStats = $isDirectorLike
            || ($viewer && $access->atLeast($viewer, 'admiral'));

        $promotionPayload = null;

        if ($viewer) {
            try {
                $promotionPayload = $promotions->profilePayload($viewer, $this->resource);
            } catch (Throwable $exception) {
                report($exception);

                $promotionPayload = [
                    'show_panel' => false,
                    'current_rank' => null,
                    'next_rank' => null,
                    'available' => false,
                    'gating_reason' => null,
                    'allowed_branch_roles' => [],
                    'can_create' => false,
                    'can_cancel' => false,
                    'can_demote' => false,
                    'expiry_minutes' => (int) config('promotions.expires_after_minutes', 240),
                    'active_offer' => null,
                ];
            }
        }

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
            'region'              => $this->region,

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
            'site_theme'          => $this->site_theme,
            'personal_tags'       => $this->personal_tags,

            'operations_completed_count' => $canViewRestrictedOperationStats ? $this->operations_completed_count : null,
            'operations_no_show_count' => $canViewRestrictedOperationStats ? $this->operations_no_show_count : null,
            'operations_excused_count' => $canViewRestrictedOperationStats ? $this->operations_excused_count : null,
            'operations_created_count' => $canViewRestrictedOperationStats ? $this->operations_created_count : null,
            'operations_canceled_count' => $canViewRestrictedOperationStats ? $this->operations_canceled_count : null,
            'operations_success_count' => $canViewRestrictedOperationStats ? $this->operations_success_count : null,
            'operations_failed_count' => $canViewRestrictedOperationStats ? $this->operations_failed_count : null,
            'operations_joined_count' => $canViewRestrictedOperationStats ? $this->operations_joined_count : null,
            'operations_left_early_count' => $canViewRestrictedOperationStats ? $this->operations_left_early_count : null,

            // RBAC roles
            'roles' => $this->roles->map(fn ($role) => [
                'slug' => $role->slug,
                'name' => $role->name,
            ])->values(),

            'squadrons' => $this->whenLoaded('squadrons', fn () => $this->squadrons->map(fn ($squadron) => [
                'id' => $squadron->id,
                'name' => $squadron->name,
                'pivot' => [
                    'membership_status' => $squadron->pivot?->membership_status,
                    'role' => $squadron->pivot?->role,
                ],
            ])->values()),

            'promotion' => $promotionPayload,
        ];
    }
}
