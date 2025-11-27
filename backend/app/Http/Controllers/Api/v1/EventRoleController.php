<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\EventRole;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EventRoleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create a new role for an event
     */
    public function store(Request $request, Event $event)
    {
        $this->authorize('manageEvent', $event);

        $validated = $request->validate([
            'role_name'         => 'required|string|max:100',
            'role_display_name' => 'nullable|string|max:150',
            'capacity'          => 'nullable|integer|min:1',
            'min_required'      => 'nullable|integer|min:0',
            'description'       => 'nullable|string|max:500',
        ]);

        // Auto-generate display name if missing
        $validated['role_display_name'] ??= ucfirst(str_replace('_', ' ', $validated['role_name']));

        $role = $event->roles()->create([
            'role_name'         => $validated['role_name'],
            'role_display_name' => $validated['role_display_name'],
            'capacity'          => $validated['capacity'] ?? null,
            'min_required'      => $validated['min_required'] ?? 0,
            'description'       => $validated['description'] ?? null,
        ]);

        return response()->json([
            'message' => 'Role created successfully',
            'role'    => $role,
        ], 201);
    }
}
