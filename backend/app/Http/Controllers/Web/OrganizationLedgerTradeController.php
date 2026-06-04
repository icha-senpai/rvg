<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\StoreLedgerTradeRequest;
use App\Models\LedgerTrade;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OrganizationLedgerTradeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function store(StoreLedgerTradeRequest $request)
    {
        $this->authorize('manage-org-ledger');

        $this->ledger->createOrgTrade($request->user(), $request->validated());

        return back()->with('success', 'Org trade run logged.');
    }

    public function update(StoreLedgerTradeRequest $request, LedgerTrade $trade)
    {
        $this->authorize('manage-org-ledger');

        $this->ledger->updateOrgTrade($request->user(), $trade, $request->validated());

        return back()->with('success', 'Org trade run updated.');
    }

    public function destroy(LedgerTrade $trade)
    {
        $this->authorize('manage-org-ledger');

        $this->ledger->deleteOrgTrade(request()->user(), $trade);

        return back()->with('success', 'Org trade run deleted.');
    }
}
