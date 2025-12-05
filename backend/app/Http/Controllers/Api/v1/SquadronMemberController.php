<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Http\Requests\SquadronMemberAddRequest;
use App\Http\Requests\SquadronMemberUpdateStatusRequest;
use App\Domain\Squadrons\MembershipService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;

class SquadronMemberController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected MembershipService $membership
    ) {}

    public function store(SquadronMemberAddRequest $request, Squadron $squadron)
    {
        $this->authorize('manageMembers', $squadron);

        try {
            $member = $this->membership->adminAddMember($squadron, $request->user_id);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->errors()['user_id'][0] ?? 'Cannot add member',
            ], 409);
        }

        return response()->json($member, 201);
    }

    public function update(
        SquadronMemberUpdateStatusRequest $request,
        Squadron $squadron,
        SquadronMember $member
    ) {
        $this->authorize('manageMembers', $squadron);

        $updated = $this->membership->adminUpdateStatus(
            $squadron,
            $member,
            $request->membership_status
        );

        return response()->json($updated);
    }

    public function destroy(Squadron $squadron, SquadronMember $member)
    {
        $this->authorize('manageMembers', $squadron);

        $this->membership->adminRemoveMember($squadron, $member);

        return response()->json(['message' => 'Member removed']);
    }

    public function join(Squadron $squadron)
    {
        $user = auth()->user();

        try {
            $member = $this->membership->userJoin($squadron, $user);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->errors()['member'][0] ?? 'Cannot join squadron',
            ], 409);
        }

        return response()->json($member, 201);
    }

    public function leave(Squadron $squadron)
    {
        $user = auth()->user();

        try {
            $this->membership->userLeave($squadron, $user);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->errors()['member'][0] ?? 'Cannot leave squadron',
            ], 404);
        }

        return response()->json(['message' => 'Left squadron successfully']);
    }
}
