<?php

namespace App\Http\Controllers\Web;

use App\Application\Squadrons\Presenters\SquadronPresenter;
use App\Http\Controllers\Controller;
use App\Models\Squadron;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SquadronLedgerController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function showById(Request $request, Squadron $squadron)
    {
        if ($squadron->slug) {
            return redirect()->route('squadrons.ledger', ['squadron' => $squadron->slug]);
        }

        return $this->renderPage($request, $squadron);
    }

    public function show(Request $request, Squadron $squadron)
    {
        return $this->renderPage($request, $squadron);
    }

    protected function renderPage(Request $request, Squadron $squadron)
    {
        $this->authorize('access-ledger');
        $this->authorize('viewLedger', $squadron);

        $viewer = $request->user();
        $wipe = (string) $request->query('wipe', 'current');
        $squadron->loadMissing(['leader.roles', 'emblem']);

        return Inertia::render('Squadrons/Ledger', [
            'squadron' => SquadronPresenter::make($squadron),
            'ledger' => $this->ledger->buildSquadronPageData($squadron, $wipe, $viewer),
            'permissions' => [
                'can_edit_ledger' => $viewer ? $viewer->can('manageLedger', $squadron) : false,
                'can_manage_ledger' => $viewer ? $viewer->can('manageLedger', $squadron) : false,
            ],
            'context' => [
                'mode' => 'squadron',
            ],
            'filters' => [
                'wipe' => $wipe,
            ],
        ]);
    }
}
