<?php

namespace App\Http\Controllers\Web;

use App\Models\Squadron;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Domain\Squadrons\SquadronService;
use Illuminate\Http\Request;

class SquadronLeaderController extends Controller
{
    public function __construct(
        protected SquadronService $squadrons
    ) {}

    public function store(Request $request, Squadron $squadron)
    {
        $this->authorize('update', $squadron);

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($data['user_id']);

        $this->squadrons->assignLeader($squadron, $user);

        return back()->with('success', 'Squadron leader assigned successfully.');
    }
}
