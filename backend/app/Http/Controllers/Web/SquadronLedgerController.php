<?php

namespace App\Http\Controllers\Web;

use App\Application\Squadrons\Presenters\SquadronPresenter;
use App\Http\Controllers\Controller;
use App\Models\Squadron;
use App\Services\LedgerReferenceService;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SquadronLedgerController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
        protected LedgerReferenceService $references,
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
        $canManageLedger = $viewer?->can('manageLedger', $squadron) ?? false;

        return Inertia::render('Squadrons/Ledger', [
            'squadron' => fn () => SquadronPresenter::make($squadron->loadMissing(['leader.roles', 'emblem'])),
            'ledger' => fn () => $this->ledger->buildSquadronPageData($squadron, $wipe, $viewer),
            ...($canManageLedger ? array_map(Inertia::optional(...), $this->references->editorReferenceLoaders()) : []),
            'permissions' => [
                'can_edit_ledger' => $canManageLedger,
                'can_manage_ledger' => $canManageLedger,
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
