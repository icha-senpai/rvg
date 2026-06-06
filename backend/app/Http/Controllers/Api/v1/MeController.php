<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMeRequest;
use App\Http\Resources\MeResource;
use Illuminate\Http\Request;

/**
 * JSON API controller for the authenticated user's own profile endpoints.
 */
class MeController extends Controller
{
    /**
     * Return the authenticated user's current profile, roles, and verification
     * state.
     */
    public function show(Request $request)
    {
        $user = $request->user()->load('roles');

        // The resource keeps the API payload shape centralized so this transport
        // endpoint only needs to load the missing relationships.
        return response()->json([
            'status'  => 'success',
            'message' => null,
            'data'    => new MeResource($user),
        ]);
    }

    /**
     * Update the authenticated user's self-service profile fields.
     */
    public function update(UpdateMeRequest $request)
    {
        $user = $request->user();

        // Keep the persisted payload restricted to the explicit self-service
        // profile fields supported by this endpoint.
        $allowed = [
            'bio',
            'timezone',
            'region',
            'favorite_ships',
            'favorite_guns',
            'primary_role',
            'secondary_role',
            'experience_ratings',
            'preferred_gameplay_style',
            'callsign',
            'typical_op_commitment',
            'preferred_roles',
            'notification_settings',
            'availability_status',
            'loa_note',
            'site_theme',
            'personal_tags',
        ];

        $safeData = collect($request->validated())
            ->only($allowed)
            ->toArray();

        $user->fill($safeData);
        $user->save();

        $user->refresh()->load('roles');

        return response()->json([
            'status'  => 'success',
            'message' => 'Profile updated.',
            'data'    => new MeResource($user),
        ]);
    }
}
