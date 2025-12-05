<?php

namespace App\Http\Controllers\Web;

use App\Models\Squadron;
use App\Models\User;
use App\Models\SquadronMember;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SquadronPromotionController extends Controller
{
    use AuthorizesRequests;

    public function promoteLieutenant(Request $request, Squadron $squadron)
    {
        $this->authorize('promoteLieutenant', $squadron);

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $member = SquadronMember::where('user_id', $data['user_id'])
            ->where('squadron_id', $squadron->id)
            ->firstOrFail();

        // Count existing lieutenants
        $ltCount = SquadronMember::where('squadron_id', $squadron->id)
            ->where('role', SquadronMember::ROLE_LIEUTENANT)
            ->count();

        if ($ltCount >= 2) {
            return back()->withErrors(['max_lt' => 'This squadron already has the maximum of two Lieutenants.']);
        }

        // Promote
        $member->update([
            'role' => SquadronMember::ROLE_LIEUTENANT,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
        ]);

        return back()->with('success', 'Lieutenant promoted successfully.');
    }
}
