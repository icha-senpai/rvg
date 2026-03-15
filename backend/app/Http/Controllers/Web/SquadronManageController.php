<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Domain\Squadrons\MembershipService;
use App\Domain\Squadrons\SquadronService;
use App\Domain\Squadrons\Presenters\SquadronPresenter;
use App\Domain\Squadrons\Presenters\SquadronMemberPresenter;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Domain\AccessControl\AccessService;

/**
 * Handles the authenticated squadron management screens and form actions.
 *
 * The controller owns transport concerns like validation, redirects, and policy
 * checks while the squadron and membership services enforce domain rules.
 */
class SquadronManageController extends Controller
{
    public function __construct(
        protected AccessService $access,
        protected MembershipService $membership,
        protected SquadronService $squadrons
    ) {}

    /**
     * Render the squadron management page for leaders and lieutenants.
     */
    public function index(Request $request, Squadron $squadron)
    {
        $user = $request->user();

        $isLeader = $this->access->isSquadronLeader($user, $squadron);
        $isLieutenant = $this->access->isSquadronLieutenant($user, $squadron);

        if (! $isLeader && ! $isLieutenant) {
            abort(403, "You cannot manage this squadron.");
        }

        return inertia('Squadron/Manage', [
            'squadron'      => SquadronPresenter::make($squadron),
            'members'       => SquadronMemberPresenter::collection(
                $this->squadrons->members($squadron)
            ),
            'isLeader'      => $isLeader,
            'isLieutenant'  => $isLieutenant,
        ]);
    }

    /**
     * Update one member's role and status from the management screen.
     */
    public function updateMember(Request $request, Squadron $squadron)
    {
        $user = $request->user();

        if (! $user->can('manageMembers', $squadron)) {
            return back()->withErrors([
                'member' => 'You cannot manage members for this squadron.',
            ]);
        }

        $data = $request->validate([
            'id'                => ['required', 'exists:squadron_members,id'],
            'role'              => ['nullable', 'string', 'in:leader,lieutenant,null'],
            'membership_status' => ['required', 'string', 'in:active,pending,banned'],
        ]);

        $member = SquadronMember::findOrFail($data['id']);

        try {
            $this->membership->updateMemberFromManage(
                $squadron,
                $member,
                $data['role'],
                $data['membership_status'],
                $user
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Member updated.');
    }

    /**
     * Remove a member from the squadron management screen.
     */
    public function removeMember(Request $request, Squadron $squadron)
    {
        $user = $request->user();

        if (! $user->can('manageMembers', $squadron)) {
            return back()->withErrors([
                'member' => 'You cannot manage members for this squadron.',
            ]);
        }

        $data = $request->validate([
            'id' => ['required', 'exists:squadron_members,id'],
        ]);

        $member = SquadronMember::findOrFail($data['id']);

        try {
            $this->membership->removeMemberFromManage($squadron, $member, $user);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Member removed.');
    }

    /**
     * Update squadron settings fields that are editable from the management page.
     */
    public function updateSettings(Request $request, Squadron $squadron)
    {
        $user = $request->user();

        if (! $user->can('update', $squadron)) {
            return back()->withErrors([
                'squadron' => 'You do not have permission to update this squadron.',
            ]);
        }

        $data = $request->validate([
            'motto'           => ['nullable', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'recruitment_propaganda' => ['nullable', 'string'],
            'primary_color'   => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'recruiting'      => ['boolean'],
        ]);

        $this->squadrons->updateSettings($squadron, $data);

        return back()->with('success', 'Squadron settings updated.');
    }

    /**
     * Let the authenticated user apply to the squadron.
     */
    public function join(Request $request, Squadron $squadron)
    {
        $user = $request->user();

        try {
            $this->membership->userJoin($squadron, $user);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Applied to squadron successfully.');
    }

    /**
     * Let the authenticated user leave the squadron.
     */
    public function leave(Request $request, Squadron $squadron)
    {
        $user = $request->user();

        try {
            $this->membership->userLeave($squadron, $user);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Left squadron successfully.');
    }

    /**
     * Demote a lieutenant back to the regular member role.
     */
    public function demoteLieutenant(Request $request, Squadron $squadron)
    {
        $user = $request->user();

        if (! $user->can('demoteLieutenant', $squadron)) {
            return back()->withErrors([
                'member' => 'You do not have permission to demote lieutenants in this squadron.',
            ]);
        }

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $targetUser = User::findOrFail($data['user_id']);

        try {
            $this->membership->demoteLieutenant($squadron, $targetUser);
        } catch (ModelNotFoundException) {
            return back()->withErrors([
                'user_id' => 'User is not a lieutenant in this squadron.',
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Lieutenant demoted successfully.');
    }

    /**
     * Choose an existing emblem media item as the squadron emblem.
     */
    public function selectEmblem(Request $request, Squadron $squadron)
    {
        $user = $request->user();

        if (! $this->squadrons->canModifyEmblem($user, $squadron)) {
            return back()->withErrors([
                'emblem_media_id' => 'You do not have permission to update the squadron emblem.',
            ]);
        }

        $data = $request->validate([
            'emblem_media_id' => ['nullable', 'integer', 'exists:media,id'],
        ]);

        try {
            $this->squadrons->selectEmblem(
                $squadron,
                isset($data['emblem_media_id']) ? (int) $data['emblem_media_id'] : null
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Squadron emblem updated.');
    }

    /**
     * Upload a new emblem image file for the squadron.
     */
    public function uploadEmblem(Request $request, Squadron $squadron)
    {
        $user = $request->user();

        if (! $this->squadrons->canModifyEmblem($user, $squadron)) {
            return back()->withErrors([
                'emblem' => 'You do not have permission to update the squadron emblem.',
            ]);
        }

        $data = $request->validate([
            'emblem' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:51200'],
        ]);

        $this->squadrons->uploadEmblem($squadron, $request->file('emblem'));

        return back()->with('success', 'Squadron emblem updated.');
    }
}
