<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Squadron;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EventController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Event::class);

        return Event::with('squadron')->get();
    }

    public function show(Event $event)
    {
        $this->authorize('view', $event);

        return $event->load([
            'squadron',
            'roles.participants.user',
            'members.user',
        ]);
    }

    public function store(Request $request, Squadron $squadron)
    {
        $this->authorize('create', Event::class);

        // Correct validation + correct column names
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',

            // Must match DB: starts_at / ends_at
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',

            'type' => 'required|in:operation,squadron_training,meeting,org_event',
            'difficulty' => 'required|in:low,medium,high',
            'operation_strictness' => 'required|in:casual,normal,strict,roleplay',

            'icon' => 'nullable|string|max:20',
            'image_url' => 'nullable|url',
            'rsvp_deadline' => 'nullable|date|before:starts_at',
            'notes' => 'nullable|string|max:2000',
        ]);

        $event = Event::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'type' => $data['type'],

            'difficulty' => $data['difficulty'],
            'operation_strictness' => $data['operation_strictness'],
            'icon' => $data['icon'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'rsvp_deadline' => $data['rsvp_deadline'] ?? null,
            'notes' => $data['notes'] ?? null,

            'squadron_id' => $squadron->id,
            'created_by' => auth()->id(),

            'status' => 'draft',
        ]);

        return response()->json($event, 201);
    }

    public function update(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',

            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date|after:starts_at',

            'type' => 'sometimes|in:operation,squadron_training,meeting,org_event',
            'difficulty' => 'sometimes|in:low,medium,high',
            'operation_strictness' => 'sometimes|in:casual,normal,strict,roleplay',

            'icon' => 'sometimes|string|max:20',
            'image_url' => 'sometimes|url',
            'rsvp_deadline' => 'sometimes|date|before:starts_at',
            'notes' => 'sometimes|string|max:2000',

            'status' => 'sometimes|in:draft,published,in_progress,completed,canceled',
        ]);

        $event->update($data);

        return response()->json($event);
    }

    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);

        $event->update(['status' => 'canceled']);
        $event->delete();

        return response()->json(['message' => 'Event canceled']);
    }

    public function updateStatus(Request $request, Event $event)
    {
        $this->authorize('manage', $event);

        $validated = $request->validate([
            'status' => 'required|in:published,in_progress,completed,canceled',
            'reason' => 'required_if:status,canceled|string|max:500',
        ]);

        try {
            $event->transitionTo(
                $validated['status'],
                $validated['reason'] ?? null
            );

            return response()->json([
                'message' => 'Event status updated',
                'event' => $event->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
