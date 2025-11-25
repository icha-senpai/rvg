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

        return $event->load('members.user');
    }

    public function store(Request $request, Squadron $squadron)
    {
        $this->authorize('create', Event::class);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'visibility' => 'required|in:squadron,open',
        ]);

        $event = Event::create([
            ...$data,
            'squadron_id' => $squadron->id,
            'created_by' => auth()->id(),
        ]);

        return response()->json($event, 201);
    }

    public function update(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $event->update($request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date',
            'visibility' => 'sometimes|in:squadron,open',
            'status' => 'sometimes|in:scheduled,active,completed,cancelled',
        ]));

        return response()->json($event);
    }

    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);

        $event->update(['status' => 'cancelled']);
        $event->delete();

        return response()->json(['message' => 'Event cancelled']);
    }
}