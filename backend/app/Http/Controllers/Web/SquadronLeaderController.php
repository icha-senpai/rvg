<?php

namespace App\Http\Controllers\Web;

use App\Models\Squadron;
use App\Models\User;
use App\Models\SquadronMember;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SquadronLeaderController extends Controller
{
    /**
     * Assign a leader to a squadron.
     */
    public function store(Request $request, Squadron $squadron)
    {
        $this->authorize('update', $squadron);

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($data['user_id']);

        // Clear old leader(s)
        SquadronMember::where('squadron_id', $squadron->id)
            ->where('role', 'leader')
            ->update(['role' => 'member']);

        // Promote new leader
        $membership = SquadronMember::updateOrCreate(
            [
                'user_id' => $user->id,
                'squadron_id' => $squadron->id,
            ],
            [
                'membership_status' => 'active',
                'role' => 'leader',
                'joined_at' => now(),
            ]
        );

        return redirect()
            ->back()
            ->with('success', 'Squadron leader assigned successfully.');
    }
}
