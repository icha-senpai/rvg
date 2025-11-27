<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\EventRole;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EventMemberController extends Controller
{
    use AuthorizesRequests;

    /**
     * Join an event (role optional)
     */
    public function join(Request $request, Event $event)
    {
        // Anyone who can "view" an event is allowed to join
        $this->authorize('view', $event);

        $validated = $request->validate([
            'event_role_id' => 'nullable|exists:event_roles,id',
            'status'        => 'nullable|in:confirmed,tentative',
            'notes'         => 'nullable|string|max:500',
        ]);

        // Already joined?
        if ($event->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['error' => 'Already joined this event'], 422);
        }

        // Validate selected role
        if (!empty($validated['event_role_id'])) {
            $role = EventRole::find($validated['event_role_id']);

            if ($role->event_id !== $event->id) {
                return response()->json(['error' => 'Invalid role for this event'], 422);
            }

            if ($role->capacity !== null) {
                $filled = $role->members()->count();
                if ($filled >= $role->capacity) {
                    return response()->json(['error' => 'This role is full'], 422);
                }
            }
        }

        // Create event member
        $member = $event->members()->create([
            'user_id'           => auth()->id(),
            'event_role_id'     => $validated['event_role_id'] ?? null,
            'attendance_status' => 'signed_up',
            'notes'             => $validated['notes'] ?? null,
            'stats'             => null,
        ]);

        return response()->json([
            'message' => 'Successfully joined event',
            'member'  => $member->load('role', 'user'),
        ], 201);
    }

    /**
     * Leave an event
     */
    public function leave(Event $event)
    {
        $user = auth()->user();

        $member = EventMember::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$member) {
            return response()->json(['message' => 'Not in event'], 404);
        }

        $member->delete();

        return response()->json(['message' => 'Left event']);
    }

    /**
     * Update a member's role (leadership only)
     */
    public function updateRole(Request $request, Event $event, EventMember $member)
    {
        $this->authorize('manageMembers', $event);

        $validated = $request->validate([
            'event_role_id' => 'nullable|exists:event_roles,id'
        ]);

        $member->update([
            'event_role_id' => $validated['event_role_id'] ?? null
        ]);

        return response()->json($member->load('role'));
    }

    /**
     * Update event member stats (leader only)
     */
    public function updateStats(Request $request, Event $event, EventMember $member)
    {
        $this->authorize('adjustStats', $event);

        $member->update([
            'stats' => $request->input('stats', []),
        ]);

        return response()->json($member);
    }

    /**
     * Join an event + auto-create a new typed role
     */
    public function joinWithRole(Request $request, Event $event)
    {
        // Anyone who can view can join
        $this->authorize('view', $event);

        $validated = $request->validate([
            'role_name' => 'required|string|max:255',
            'notes'     => 'nullable|string|max:500',
            'status'    => 'nullable|in:confirmed,tentative'
        ]);

        // Prevent duplicate joining
        if ($event->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['error' => 'Already joined this event'], 422);
        }

        // Lookup existing
        $role = $event->roles()->where('role_name', $validated['role_name'])->first();

        // Auto-create if needed
        if (!$role) {
            $role = $event->roles()->create([
                'role_name'         => $validated['role_name'],
                'role_display_name' => ucfirst(str_replace('_', ' ', $validated['role_name'])),
                'capacity'          => null,
                'min_required'      => 0,
                'description'       => null,
            ]);
        }

        $member = $event->members()->create([
            'user_id'           => auth()->id(),
            'event_role_id'     => $role->id,
            'attendance_status' => 'signed_up',
            'notes'             => $validated['notes'] ?? null,
            'stats'             => null,
        ]);

        return response()->json([
            'message'     => 'Joined event with role',
            'participant' => $member->load('role', 'user')
        ], 201);
    }
}
