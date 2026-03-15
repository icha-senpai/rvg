<?php

namespace App\Http\Controllers\Api\v1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePreferencesRequest;
use App\Models\MemberPreference;
use Illuminate\Http\Request;

/**
 * JSON API controller for the authenticated user's preference record.
 */
class MePreferenceController extends Controller
{
    /**
     * Return the authenticated user's preferences, creating a default row when
     * one does not exist yet.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        $prefs = $user->preferences;

        if (! $prefs) {
            $prefs = $user->preferences()->create(MemberPreference::defaults());
        }

        return ApiResponse::success(
            null,
            ['preferences' => $this->presentPreferences($prefs)]
        );
    }

    /**
     * Update the authenticated user's preferences.
     */
    public function update(UpdatePreferencesRequest $request)
    {
        $user = $request->user();

        $prefs = $user->preferences;

        if (! $prefs) {
            $prefs = $user->preferences()->create(MemberPreference::defaults());
        }

        $data = $request->validated();

        // Keep the saved payload restricted to the explicit preference fields
        // supported by this endpoint.
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

        return ApiResponse::success(
            'Preferences updated.',
            ['preferences' => $this->presentPreferences($prefs)]
        );
    }

    /**
     * Normalize the preference payload returned by both endpoints.
     */
    protected function presentPreferences(MemberPreference $prefs): array
    {
        return [
            'status'          => $prefs->status,
            'loa_until'       => $prefs->loa_until,
            'preferred_times' => $prefs->preferred_times,
            'focus'           => $prefs->focus,
            'roles'           => $prefs->roles,
            'notes'           => $prefs->notes,
        ];
    }
}
