<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DiscordSelfRoleService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MemberSettingsController extends Controller
{
    public function __construct(
        protected DiscordSelfRoleService $discordRoles,
    ) {}

    public function show(Request $request)
    {
        return Inertia::render('Member/Settings', [
            'discordRoleSettings' => $this->discordRoles->settingsPayload($request->user()),
        ]);
    }
}
