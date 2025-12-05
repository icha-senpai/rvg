<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SquadronRankController extends Controller
{
    public function promote(Request $request)
    {
        $request->validate([
            'squadron_id' => 'required|integer',
            'user_id' => 'required|integer',
        ]);

        $squadron = Squadron::findOrFail($request->squadron_id);
        $user = User::findOrFail($request->user_id);

        Gate::authorize('manageRanks', $squadron);

        // enforce squadron membership
        $membership = SquadronMember::where('user_id', $user->id)
            ->where('squadron_id', $squadron->id)
            ->firstOrFail();

        // count current lieutenants
        $currentLtCount = SquadronMember::where('squadron_id', $squadron->id)
            ->where('role', 'lieutenant')
            ->count();

        if ($currentLtCount >= 2) {
            return response()->json([
                'message' => 'This squadron already has the maximum of 2 lieutenants.'
            ], 422);
        }

        $membership->role = 'lieutenant';
        $membership->save();

        return response()->json(['success' => true]);
    }

    public function demote(Request $request)
    {
        $request->validate([
            'squadron_id' => 'required|integer',
            'user_id' => 'required|integer',
        ]);

        $squadron = Squadron::findOrFail($request->squadron_id);
        $user = User::findOrFail($request->user_id);

        Gate::authorize('manageRanks', $squadron);

        $membership = SquadronMember::where('user_id', $user->id)
            ->where('squadron_id', $squadron->id)
            ->firstOrFail();

        $membership->role = 'member';
        $membership->save();

        return response()->json(['success' => true]);
    }
}
