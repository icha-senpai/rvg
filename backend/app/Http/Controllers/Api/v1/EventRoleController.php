<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRole;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EventRoleController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Event $event)
    {
        // IMPORTANT: this must match the policy method: manage()
        $this->authorize('manage', $event);

        $data = $request->validate([
            'role_name' => 'required|string|max:255',
            'role_display_name' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'min_required' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $role = $event->roles()->create([
            'role_name' => $data['role_name'],
            'role_display_name' => $data['role_display_name']
                ?? ucfirst(str_replace('_', ' ', $data['role_name'])),
            'capacity' => $data['capacity'] ?? null,
            'min_required' => $data['min_required'] ?? 0,
            'description' => $data['description'] ?? null,
        ]);

        return response()->json($role, 201);
    }
}
