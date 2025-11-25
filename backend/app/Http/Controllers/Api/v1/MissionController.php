<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Models\Mission;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MissionController extends Controller
{
    use AuthorizesRequests;
    public function index()
    {
        $this->authorize('viewAny', Mission::class);

        return Mission::with('creator')->get();
    }

    public function show(Mission $mission)
    {
        $this->authorize('view', $mission);

        return $mission->load('members.user');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Mission::class);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'type' => 'required|string',
            'slots' => 'nullable|array',
        ]);

        $mission = Mission::create([
            ...$data,
            'created_by' => auth()->id(),
        ]);

        return response()->json($mission, 201);
    }

    public function update(Request $request, Mission $mission)
    {
        $this->authorize('update', $mission);

        $mission->update($request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date',
            'type' => 'sometimes|string',
            'slots' => 'sometimes|array',
            'status' => 'sometimes|in:scheduled,active,completed,cancelled',
        ]));

        return response()->json($mission);
    }

    public function destroy(Mission $mission)
    {
        $this->authorize('delete', $mission);

        $mission->update(['status' => 'cancelled']);
        $mission->delete();

        return response()->json(['message' => 'Mission cancelled']);
    }
}