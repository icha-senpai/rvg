<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Squadrons\MembershipService;
use App\Http\Controllers\Controller;
use App\Models\Squadron;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Handles the legacy admin endpoints for promoting or demoting squadron member
 * ranks.
 */
class SquadronRankController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected MembershipService $membership
    ) {}

    /**
     * Promote a squadron member to lieutenant.
     */
    public function promote(Request $request)
    {
        $request->validate([
            'squadron_id' => 'required|integer',
            'user_id' => 'required|integer',
        ]);

        $squadron = Squadron::findOrFail($request->squadron_id);
        $user = User::findOrFail($request->user_id);

        $this->authorize('promoteLieutenant', $squadron);

        try {
            $this->membership->promoteLieutenant($squadron, $user);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'User is not a member of this squadron.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Cannot promote member.',
            ], 422);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Demote a squadron member back to the default member rank.
     */
    public function demote(Request $request)
    {
        $request->validate([
            'squadron_id' => 'required|integer',
            'user_id' => 'required|integer',
        ]);

        $squadron = Squadron::findOrFail($request->squadron_id);
        $user = User::findOrFail($request->user_id);

        $this->authorize('demoteLieutenant', $squadron);

        try {
            $this->membership->demoteLieutenant($squadron, $user);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'User is not a lieutenant in this squadron.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Cannot demote member.',
            ], 422);
        }

        return response()->json(['success' => true]);
    }
}