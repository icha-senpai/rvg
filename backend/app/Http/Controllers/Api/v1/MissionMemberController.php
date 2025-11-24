<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\MissionMember;

class MissionMemberController extends Controller
{
    public function join(Mission $mission)
    {
        $user = auth()->user();

        $exists = MissionMember::where('mission_id', $mission->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already in mission'], 409);
        }

        $member = MissionMember::create([
            'mission_id' => $mission->id,
            'user_id' => $user->id,
            'attendance_status' => 'signed_up',
        ]);

        return response()->json($member, 201);
    }

    public function leave(Mission $mission)
    {
        $user = auth()->user();

        $member = MissionMember::where('mission_id', $mission->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$member) {
            return response()->json(['message' => 'Not in mission'], 404);
        }

        $member->delete();

        return response()->json(['message' => 'Left mission']);
    }

    public function updateSlot(Request $request, Mission $mission, MissionMember $member)
    {
        $this->authorize('manageMembers', $mission);

        $member->update($request->validate([
            'slot' => 'nullable|string|max:255',
        ]));

        return response()->json($member);
    }

    public function updateStats(Request $request, Mission $mission, MissionMember $member)
    {
        $this->authorize('adjustStats', $mission);

        $member->update([
            'stats' => $request->input('stats', []),
        ]);

        return response()->json($member);
    }
}