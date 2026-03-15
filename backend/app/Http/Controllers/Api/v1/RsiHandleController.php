<?php

namespace App\Http\Controllers\Api\v1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RsiChangeRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

/**
 * JSON API controller for RSI handle change requests and officer review
 * actions.
 */
class RsiHandleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Return the RSI handle change requests queue for officer review.
     */
    public function index()
    {
        // Officer-only access is enforced by route middleware before the request
        // reaches this transport controller.
        return ApiResponse::success(
            null,
            [
                'requests' => RsiChangeRequest::with(['user', 'approver'])
                    ->latest()
                    ->get(),
            ]
        );
    }

    /**
     * Create a new RSI handle change request for the authenticated user.
     */
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
            return ApiResponse::error('You already have a pending request.', null, 422);
        }

        $requestModel = RsiChangeRequest::create([
            'user_id' => Auth::id(),
            'requested_rsi_handle' => $validated['requested_rsi_handle'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return ApiResponse::success(
            'RSI handle change request created.',
            ['request' => $requestModel],
            201
        );
    }

    /**
     * Approve a pending RSI handle change request and update the user's stored
     * RSI handle.
     */
    public function approve(Request $req, RsiChangeRequest $change)
    {
        $this->authorize('user.manage');

        if ($change->status !== 'pending') {
            return ApiResponse::error('Already processed', null, 422);
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

        return ApiResponse::success('RSI handle updated');
    }

    /**
     * Reject a pending RSI handle change request.
     */
    public function reject(Request $req, RsiChangeRequest $change)
    {
        $this->authorize('user.manage');

        if ($change->status !== 'pending') {
            return ApiResponse::error('Already processed', null, 422);
        }

        $change->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        return ApiResponse::success('Request rejected');
    }
}
