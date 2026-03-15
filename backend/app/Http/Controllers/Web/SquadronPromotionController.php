<?php

namespace App\Http\Controllers\Web;

use App\Models\Squadron;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Domain\Squadrons\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Handles the web action for promoting a squadron member to lieutenant.
 */
class SquadronPromotionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected MembershipService $membership
    ) {}

    /**
     * Promote the requested squadron member to lieutenant.
     *
     * Validation and authorization stay in the controller, while the membership
     * service enforces squadron-level promotion rules and side effects.
     */
    public function promoteLieutenant(Request $request, Squadron $squadron)
    {
        $this->authorize('promoteLieutenant', $squadron);

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($data['user_id']);

        try {
            $this->membership->promoteLieutenant($squadron, $user);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Lieutenant promoted successfully.');
    }
}
