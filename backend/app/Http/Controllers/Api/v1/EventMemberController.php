<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\EventMember;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EventMemberController extends Controller
{
    use AuthorizesRequests;
    public function join(Event $event)
    {
        $this->authorize('view', $event);

        $user = auth()->user();

        $exists = EventMember::where('event_id', $event->id)
            ->where('user_id', $user->id)->exists();

        if ($exists) {
            return response()->json(['message' => 'Already joined'], 409);
        }

        $member = EventMember::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => 'participant',
            'attendance_status' => 'signed_up',
        ]);

        return response()->json($member, 201);
    }

    public function leave(Event $event)
    {
        $user = auth()->user();

        $member = EventMember::where('event_id', $event->id)
            ->where('user_id', $user->id)->first();

        if (!$member) {
            return response()->json(['message' => 'Not in event'], 404);
        }

        $member->delete();

        return response()->json(['message' => 'Left event']);
    }

    public function updateRole(Request $request, Event $event, EventMember $member)
    {
        $this->authorize('manageMembers', $event);

        $member->update($request->validate([
            'role' => 'required|in:leader,assistant,participant,observer'
        ]));

        return response()->json($member);
    }

    public function updateStats(Request $request, Event $event, EventMember $member)
    {
        $this->authorize('adjustStats', $event);

        $member->update([
            'stats' => $request->input('stats', []),
        ]);

        return response()->json($member);
    }
}
