<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Squadron;
use Inertia\Inertia;

class SquadronPageController extends Controller
{
    public function show(Squadron $squadron)
    {
        // Auth check is already handled by middleware
        // Authorization happens in the API, not here

        return Inertia::render('Squadrons/Show', [
            'squadronId' => $squadron->id,
        ]);
    }
}
