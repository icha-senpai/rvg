<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Http\Requests\SquadronMemberAddRequest;
use App\Http\Requests\SquadronMemberUpdateStatusRequest;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SquadronMemberController extends Controller   
{
    use AuthorizesRequests;
    /**
     * Add a member to a squadron (admin or director action).
     */
    public function store(SquadronMemberAddRequest $request, Squadron $squadron)
    {
        $this->authorize('manageMembers', $squadron);

        $exists = SquadronMember::where('user_id', $request->user_id)
            ->where('squadron_id', $squadron->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'User already in squadron',
            ], 409);
        }

        $member = SquadronMember::create([
            'user_id' => $request->user_id,
            'squadron_id' => $squadron->id,
            'membership_status' => 'active',
            'joined_at' => now(),
        ]);

        return response()->json($member, 201);
    }

    /**
     * Update membership status.
     */
    public function update(
        SquadronMemberUpdateStatusRequest $request,
        Squadron $squadron,
        SquadronMember $member
    ) {
        $this->authorize('manageMembers', $squadron);

        if ($member->squadron_id !== $squadron->id) {
            return response()->json(['message' => 'Member does not belong to this squadron'], 409);
        }

        $member->update([
            'membership_status' => $request->membership_status,
        ]);

        return response()->json($member);
    }

    /**
     * Remove member (kick from squadron).
     */
    public function destroy(Squadron $squadron, SquadronMember $member)
    {
        $this->authorize('manageMembers', $squadron);

        if ($member->squadron_id !== $squadron->id) {
            return response()->json(['message' => 'Member does not belong to this squadron'], 409);
        }

        $member->update([
            'left_at' => now(),
            'membership_status' => 'pending',
        ]);

        $member->delete();

        return response()->json(['message' => 'Member removed']);
    }

    /**
     * User joins a squadron themselves.
     */
    public function join(Squadron $squadron)
    {
        $user = auth()->user();

        // Prevent joining multiple squadrons
        $already = SquadronMember::where('user_id', $user->id)->exists();
        if ($already) {
            return response()->json(['message' => 'Already in a squadron'], 409);
        }

        $member = SquadronMember::create([
            'user_id' => $user->id,
            'squadron_id' => $squadron->id,
            'membership_status' => 'pending',
            'joined_at' => now(),
        ]);

        return response()->json($member, 201);
    }

    /**
     * User leaves their squadron.
     */
    public function leave(Squadron $squadron)
    {
        $user = auth()->user();

        $member = SquadronMember::where('user_id', $user->id)
            ->where('squadron_id', $squadron->id)
            ->first();

        if (!$member) {
            return response()->json(['message' => 'Not a member of this squadron'], 404);
        }

        $member->update([
            'left_at' => now(),
        ]);

        $member->delete();

        return response()->json(['message' => 'Left squadron successfully']);
    }
}
