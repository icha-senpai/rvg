<?php

namespace App\Http\Controllers\Api\v1;

use App\Application\Squadrons\Presenters\SquadronMemberPresenter;
use App\Domain\AccessControl\AccessService;
use App\Domain\Squadrons\MembershipService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SquadronMemberAddRequest;
use App\Http\Requests\SquadronMemberUpdateStatusRequest;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * JSON API controller for squadron membership management and self-service
 * membership actions.
 */
class SquadronMemberController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected AccessService $access,
        protected MembershipService $membership
    ) {}

    /**
     * Add a user directly to the squadron through the API.
     */
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
            SquadronMemberPresenter::make($member->load('user')),
            201
        );
    }

    /**
     * Update only the membership status for one squadron member.
     */
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
            SquadronMemberPresenter::make($updated->load('user')),
            201
        );
    }

    /**
     * Remove a member from the squadron through the API.
     */
    public function destroy(Squadron $squadron, SquadronMember $member)
    {
        $user = Auth::user();

        $this->authorize('manageMembers', $squadron);

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            $this->membership->removeMemberFromManage($squadron, $member, $user);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Cannot remove member.',
            ], 409);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Member removed',
        ]);
    }

    /**
     * Let the authenticated user join the squadron through the API.
     */
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
            SquadronMemberPresenter::make($member->load('user')),
            201
        );
    }

    /**
     * Let the authenticated user leave the squadron through the API.
     */
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

    /**
     * Promote the given user to lieutenant through the API.
     */
    public function promoteLieutenant(Squadron $squadron, User $user)
    {
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
            'member' => SquadronMemberPresenter::make($member->load('user')),
        ]);
    }

    /**
     * Demote the given lieutenant back to a regular member through the API.
     */
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
