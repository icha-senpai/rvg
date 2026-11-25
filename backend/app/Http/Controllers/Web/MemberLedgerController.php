<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\LedgerReferenceService;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MemberLedgerController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
        protected LedgerReferenceService $references,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('access-ledger');

        $user = $request->user();
        $wipe = trim((string) $request->query('wipe', 'current'));

        return Inertia::render('Member/Ledger', [
            'ledger' => fn () => $this->ledger->buildPageData($user, $wipe),
            ...array_map(Inertia::optional(...), $this->references->editorReferenceLoaders()),
            'permissions' => [
                'can_edit_ledger' => true,
            ],
            'context' => [
                'mode' => 'personal',
            ],
            'filters' => [
                'wipe' => $wipe,
            ],
        ]);
    }
}
