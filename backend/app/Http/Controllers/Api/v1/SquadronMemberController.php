<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Http\Requests\SquadronMemberAddRequest;
use App\Http\Requests\SquadronMemberUpdateStatusRequest;
use App\Domain\Squadrons\MembershipService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

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
                'status' => 'error',
                'message' => $e->errors()['user_id'][0] ?? 'Cannot add member',
            ], 409);
        }

        return response()->json(
            \App\Domain\Squadrons\Presenters\SquadronMemberPresenter::make(
                $member->load('user')
            ),
            201
        );
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

        return response()->json(
            \App\Domain\Squadrons\Presenters\SquadronMemberPresenter::make(
                $member->load('user')
            ),
            201
        );
    }

    public function destroy(Squadron $squadron, SquadronMember $member)
    {
        $user = Auth::user();

        $this->authorize('manageMembers', $squadron);

        if (
            $user instanceof User
            && $user->isSquadronLieutenant($squadron)
            && (
                $member->user_id === $squadron->leader_id
                || $member->role === SquadronMember::ROLE_LEADER
            )
        ) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Lieutenants cannot remove the squadron leader.',
            ], 403);
        }

        $this->membership->adminRemoveMember($squadron, $member);

        return response()->json([
            'status' => 'success',
            'message' => 'Member removed',
        ]);
    }

    public function join(Squadron $squadron)
    {
        $user = Auth::user();

        try {
            $member = $this->membership->userJoin($squadron, $user);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->errors()['member'][0] ?? 'Cannot join squadron',
            ], 409);
        }

        return response()->json(
            \App\Domain\Squadrons\Presenters\SquadronMemberPresenter::make(
                $member->load('user')
            ),
            201
        );
    }

    public function leave(Squadron $squadron)
    {
        $user = Auth::user();

        try {
            $this->membership->userLeave($squadron, $user);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->errors()['member'][0] ?? 'Cannot leave squadron',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Left squadron successfully',
        ]);
    }
    public function promoteLieutenant(
        Squadron $squadron,
        User $user
    ) {
        $this->authorize('promoteLieutenant', $squadron);

        try {
            $member = $this->membership->promoteLieutenant($squadron, $user);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'User is not a member of this squadron.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Cannot promote member.',
            ], 409);
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'member' => \App\Domain\Squadrons\Presenters\SquadronMemberPresenter::make(
                $member->load('user')
            ),
        ]);
    }
    public function demoteLieutenant(Squadron $squadron, User $user)
    {
        $this->authorize('demoteLieutenant', $squadron);

        try {
            $this->membership->demoteLieutenant($squadron, $user);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'User is not a lieutenant in this squadron.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Cannot demote member.',
            ], 409);
        }

        return response()->noContent();
    }



}
