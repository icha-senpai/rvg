<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDiscordSelfRolesRequest;
use App\Services\DiscordSelfRoleService;
use Illuminate\Http\Request;

class MemberDiscordRoleController extends Controller
{
    public function __construct(
        protected DiscordSelfRoleService $discordRoles,
    ) {}

    public function sync(Request $request)
    {
        $this->discordRoles->sync($request->user());

        return back()->with('success', 'Discord roles synced.');
    }

    public function update(UpdateDiscordSelfRolesRequest $request)
    {
        $this->discordRoles->update(
            $request->user(),
            $request->validated('branch_role_ids', []),
            $request->validated('player_role_ids', [])
        );

        return back()->with('success', 'Discord roles updated.');
    }
}
