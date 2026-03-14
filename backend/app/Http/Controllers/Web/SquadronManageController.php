<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\Media\MediaService;
use App\Models\Squadron;
use App\Models\Media;
use App\Models\SquadronMember;
use App\Domain\Squadrons\MembershipService;
use App\Domain\Squadrons\SquadronService;
use App\Domain\Squadrons\Presenters\SquadronPresenter;
use App\Domain\Squadrons\Presenters\SquadronMemberPresenter;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SquadronManageController extends Controller
{
    public function __construct(
        protected MembershipService $membership,
        protected SquadronService  $squadrons,
        protected MediaService $media
    ) {}

    public function index(Request $request, Squadron $squadron)
    {
        $user = $request->user();

        if (! $user->isSquadronLeader($squadron) && ! $user->isSquadronLieutenant($squadron)) {
            abort(403, "You cannot manage this squadron.");
        }

        return inertia('Squadron/Manage', [
            'squadron'      => SquadronPresenter::make($squadron),
            'members'       => SquadronMemberPresenter::collection(
                $this->squadrons->members($squadron)
            ),
            'isLeader'      => $user->isSquadronLeader($squadron),
            'isLieutenant'  => $user->isSquadronLieutenant($squadron),
        ]);
    }

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

        if (
            $user instanceof User
            && $user->isSquadronLieutenant($squadron)
            && (
                $member->user_id === $squadron->leader_id
                || $member->role === SquadronMember::ROLE_LEADER
            )
        ) {
            return back()->withErrors([
                'member' => 'Lieutenants cannot remove the squadron leader.',
            ]);
        }

        try {
            $this->membership->adminRemoveMember($squadron, $member);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Member removed.');
    }

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

    public function selectEmblem(Request $request, Squadron $squadron)
    {
        $user = $request->user();

        if (! $this->canModifyEmblem($user, $squadron)) {
            return back()->withErrors([
                'emblem_media_id' => 'You do not have permission to update the squadron emblem.',
            ]);
        }

        $data = $request->validate([
            'emblem_media_id' => ['nullable', 'integer', 'exists:media,id'],
        ]);

        $emblemMediaId = $data['emblem_media_id'] ?? null;

        if ($emblemMediaId === null) {
            Media::where('mediable_type', Squadron::class)
                ->where('mediable_id', $squadron->id)
                ->where('collection', Media::COLLECTION_SQUADRON_EMBLEM)
                ->update([
                    'mediable_type' => null,
                    'mediable_id' => null,
                ]);

            return back()->with('success', 'Squadron emblem updated.');
        }

        $media = Media::findOrFail((int) $emblemMediaId);

        if ($media->collection !== Media::COLLECTION_SQUADRON_EMBLEM) {
            return back()->withErrors([
                'emblem_media_id' => 'Selected media is not a squadron emblem.',
            ]);
        }

        if ($media->mediable_type && ! (
            $media->mediable_type === Squadron::class
            && (int) $media->mediable_id === (int) $squadron->id
        )) {
            return back()->withErrors([
                'emblem_media_id' => 'Selected media is already attached to another record.',
            ]);
        }

        $this->media->attach($media, $squadron, true);

        return back()->with('success', 'Squadron emblem updated.');
    }

    public function uploadEmblem(Request $request, Squadron $squadron)
    {
        $user = $request->user();

        if (! $this->canModifyEmblem($user, $squadron)) {
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

    protected function canModifyEmblem(User $user, Squadron $squadron): bool
    {
        return $user->isSquadronLeader($squadron)
            || $user->hasRole('director')
            || $user->hasRole('tech_director')
            || (
                (int) ($user->rank_level ?? 0) >= 2
                && $user->squadronMemberships()->active()->where('squadron_id', $squadron->id)->exists()
            );
    }
}
