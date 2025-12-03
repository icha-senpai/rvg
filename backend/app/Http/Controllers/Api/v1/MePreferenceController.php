<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePreferencesRequest;
use App\Models\MemberPreference;
use Illuminate\Http\Request;

class MePreferenceController extends Controller
{
    /**
     * GET /api/v1/me/preferences
     * Return the authenticated user's preferences, or create defaults if missing.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // Get existing preferences or create defaults
        $prefs = $user->preferences;

        if (!$prefs) {
            $prefs = $user->preferences()->create(MemberPreference::defaults());
        }

        return response()->json([
            'data' => [
                'status'          => $prefs->status,
                'loa_until'       => $prefs->loa_until,
                'preferred_times' => $prefs->preferred_times,
                'focus'           => $prefs->focus,
                'roles'           => $prefs->roles,
                'notes'           => $prefs->notes,
            ],
        ]);
    }

    /**
     * PUT/PATCH /api/v1/me/preferences
     * Update the authenticated user's preferences.
     */
    public function update(UpdatePreferencesRequest $request)
    {
        $user = $request->user();

        $prefs = $user->preferences;

        if (!$prefs) {
            $prefs = $user->preferences()->create(MemberPreference::defaults());
        }

        $data = $request->validated();

        // Only accept these keys
        $allowed = [
            'status',
            'loa_until',
            'preferred_times',
            'focus',
            'roles',
            'notes',
        ];

        $safeData = collect($data)
            ->only($allowed)
            ->toArray();

        $prefs->fill($safeData);
        $prefs->save();

        return response()->json([
            'message' => 'Preferences updated.',
            'data' => [
                'status'          => $prefs->status,
                'loa_until'       => $prefs->loa_until,
                'preferred_times' => $prefs->preferred_times,
                'focus'           => $prefs->focus,
                'roles'           => $prefs->roles,
                'notes'           => $prefs->notes,
            ],
        ]);
    }
}
