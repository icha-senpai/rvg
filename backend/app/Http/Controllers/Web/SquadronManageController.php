<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Squadron;
use App\Models\SquadronMember;
use Illuminate\Http\Request;

class SquadronManageController extends Controller
{
    public function index(Request $request, Squadron $squadron)
    {
        $user = $request->user();

        // Access control:
        if (! $user->isSquadronLeader($squadron) && ! $user->isSquadronLieutenant($squadron)) {
            abort(403, "You cannot manage this squadron.");
        }

        $members = $squadron->members()->with('user')->get();

        return inertia('Squadron/Manage', [
            'squadron' => $squadron,
            'members' => $members,
            'isLeader' => $user->isSquadronLeader($squadron),
            'isLieutenant' => $user->isSquadronLieutenant($squadron)
        ]);
    }


    public function updateMember(Request $request, Squadron $squadron)
    {
        $user = $request->user();
        if (! $user->isSquadronLeader($squadron)) {
            abort(403);
        }

        $data = $request->validate([
            'id' => ['required', 'exists:squadron_members,id'],
            'role' => ['nullable', 'string', 'in:leader,lieutenant,null'],
            'membership_status' => ['required', 'string', 'in:active,pending,banned'],
        ]);

        $member = SquadronMember::findOrFail($data['id']);

        // Prevent leader from demoting themselves
        if ($member->user_id === $user->id && $data['role'] !== 'leader') {
            abort(403, "You cannot demote yourself.");
        }

        $member->update([
            'role' => $data['role'] === 'null' ? null : $data['role'],
            'membership_status' => $data['membership_status'],
        ]);

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

        // Leaders cannot remove themselves
        if ($member->user_id === $user->id) {
            abort(403, "You cannot remove yourself.");
        }

        $member->delete();

        return back()->with('success', 'Member removed.');
    }
    public function updateSettings(Request $request, Squadron $squadron)
    {
    $user = $request->user();

    if (! $user->isSquadronLeader($squadron)) {
        abort(403);
    }

    $data = $request->validate([
        'motto' => ['nullable', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'primary_color' => ['nullable', 'string', 'max:20'],
        'secondary_color' => ['nullable', 'string', 'max:20'],
        'recruiting' => ['boolean'],
    ]);

    $squadron->update($data);

    return back()->with('success', 'Squadron settings updated.');
    }
    public function uploadEmblem(Request $request, Squadron $squadron)
    {
    $user = $request->user();

    if (! $user->isSquadronLeader($squadron)) {
        abort(403);
    }

    $data = $request->validate([
        'emblem' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'], // 2MB
    ]);

    // Store file
    $path = $request->file('emblem')->store('squadrons', 'public');

    // Update squadron
    $squadron->update([
        'emblem_path' => $path,
    ]);

    return back()->with('success', 'Squadron emblem updated.');
    }

}
