<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMeRequest;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * GET /api/v1/me
     * Return the authenticated user's profile + roles.
     */
    public function show(Request $request)
    {
        $user = $request->user()->load('roles');

        return response()->json([
            'data' => [
                'id'                  => $user->id,
                'name'                => $user->name,
                'email'               => $user->email,
                'rank'                => $user->rank,
                'rank_level'          => $user->rank_level,
                'rank_name'           => $user->rank_name ?? null,

                // Discord
                'discord_id'          => $user->discord_id,
                'discord_name'        => $user->discord_name,
                'discord_avatar'      => $user->discord_avatar,

                // RSI (read-only here)
                'rsi_handle'          => $user->rsi_handle,
                'rsi_verified_at'     => $user->rsi_verified_at,

                // Self-service profile
                'bio'                 => $user->bio,
                'timezone'            => $user->timezone,
                'preferred_roles'     => $user->preferred_roles ?? [],
                'notification_settings' => $user->notification_settings ?? [],
                'availability_status' => $user->availability_status,
                'loa_note'            => $user->loa_note,
                'personal_tags'       => $user->personal_tags ?? [],

                // Roles (RBAC)
                'roles'               => $user->roles->map(fn ($role) => [
                    'slug' => $role->slug,
                    'name' => $role->name,
                ])->values(),
            ],
        ]);
    }

    /**
     * PUT /api/v1/me
     * Update self-service fields for the authenticated user.
     */
    public function update(UpdateMeRequest $request)
    {
        $user = $request->user();

        $data = $request->validated();

        // Only allow these specific keys to be mass-assigned, even if
        // something weird slips through from the client.
        $allowed = [
            'bio',
            'timezone',
            'preferred_roles',
            'notification_settings',
            'availability_status',
            'loa_note',
            'personal_tags',
        ];

        $safeData = collect($data)
            ->only($allowed)
            ->toArray();

        $user->fill($safeData);
        $user->save();

        $user->refresh()->load('roles');

        return response()->json([
            'message' => 'Profile updated.',
            'data'    => [
                'id'                  => $user->id,
                'name'                => $user->name,
                'bio'                 => $user->bio,
                'timezone'            => $user->timezone,
                'preferred_roles'     => $user->preferred_roles ?? [],
                'notification_settings' => $user->notification_settings ?? [],
                'availability_status' => $user->availability_status,
                'loa_note'            => $user->loa_note,
                'personal_tags'       => $user->personal_tags ?? [],
                'roles'               => $user->roles->map(fn ($role) => [
                    'slug' => $role->slug,
                    'name' => $role->name,
                ])->values(),
            ],
        ]);
    }
}
