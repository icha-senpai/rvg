<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Domain\Squadrons\MembershipService;
use App\Domain\Squadrons\SquadronService;
use App\Domain\Squadrons\Presenters\SquadronPresenter;
use App\Domain\Squadrons\Presenters\SquadronMemberPresenter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SquadronManageController extends Controller
{
    public function __construct(
        protected MembershipService $membership,
        protected SquadronService  $squadrons
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

        if (! $user->isSquadronLeader($squadron)) {
            abort(403);
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

        if (! $user->isSquadronLeader($squadron)) {
            abort(403);
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

    public function updateSettings(Request $request, Squadron $squadron)
    {
        $user = $request->user();

        if (! $user->isSquadronLeader($squadron)) {
            abort(403);
        }

        $data = $request->validate([
            'motto'           => ['nullable', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'primary_color'   => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'recruiting'      => ['boolean'],
        ]);

        $this->squadrons->updateSettings($squadron, $data);

        return back()->with('success', 'Squadron settings updated.');
    }

    public function uploadEmblem(Request $request, Squadron $squadron)
    {
        $user = $request->user();

        if (! $user->isSquadronLeader($squadron)) {
            abort(403);
        }

        $data = $request->validate([
            'emblem' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $this->squadrons->uploadEmblem($squadron, $request->file('emblem'));

        return back()->with('success', 'Squadron emblem updated.');
    }
}
