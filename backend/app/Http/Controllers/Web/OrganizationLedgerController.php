<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrganizationLedgerController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('access-ledger');
        $this->authorize('manage-org-ledger');

        $wipe = trim((string) $request->query('wipe', 'current'));

        return Inertia::render('Organization/Ledger', [
            'ledger' => $this->ledger->buildOrganizationPageData($request->user(), $wipe),
            'permissions' => [
                'can_edit_ledger' => true,
                'can_manage_ledger' => true,
            ],
            'context' => [
                'mode' => 'organization',
            ],
            'filters' => [
                'wipe' => $wipe,
            ],
        ]);
    }
}
