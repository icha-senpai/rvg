<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMeRequest;
use App\Http\Resources\MeResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * GET /api/v1/me
     * Return the authenticated user's profile + roles + verification states.
     */
    public function show(Request $request)
    {
        $user = $request->user()->load('roles');

        // Ensure RSI/Discord fields are present in the response payload
        // by passing them explicitly into MeResource.
        return new MeResource($user);
    }

    /**
     * PUT /api/v1/me
     * Update self-service fields for the authenticated user.
     */
    public function update(UpdateMeRequest $request)
    {
        $user = $request->user();

        // Strict extraction of allowed fields
        $allowed = [
            'bio',
            'timezone',
            'preferred_roles',
            'notification_settings',
            'availability_status',
            'loa_note',
            'personal_tags',
        ];

        $safeData = collect($request->validated())
            ->only($allowed)
            ->toArray();

        // Update user profile
        $user->fill($safeData);
        $user->save();

        $user->refresh()->load('roles');

        return response()->json([
            'message' => 'Profile updated.',
            'data'    => new MeResource($user),
        ]);
    }
}
