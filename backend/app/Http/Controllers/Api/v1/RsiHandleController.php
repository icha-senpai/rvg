<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RsiChangeRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class RsiHandleController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        // Officers only, enforced by route middleware
        return RsiChangeRequest::with(['user', 'approver'])
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'requested_rsi_handle' => 'required|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        $exists = RsiChangeRequest::where('user_id', Auth::id())
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'You already have a pending request.'
            ], 422);
        }

        $requestModel = RsiChangeRequest::create([
            'user_id' => Auth::id(),
            'requested_rsi_handle' => $validated['requested_rsi_handle'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($requestModel, 201);
    }

    public function approve(Request $req, RsiChangeRequest $change)
    {
        $this->authorize('user.manage'); // RBAC permission

        if ($change->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Already processed',
            ], 422);
        }

        $change->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        $change->user->update([
            'rsi_handle' => $change->requested_rsi_handle,
            'rsi_verified_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'RSI handle updated',
        ]);
    }

    public function reject(Request $req, RsiChangeRequest $change)
    {
        $this->authorize('user.manage');

        if ($change->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Already processed',
            ], 422);
        }

        $change->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Request rejected',
        ]);
    }
}
