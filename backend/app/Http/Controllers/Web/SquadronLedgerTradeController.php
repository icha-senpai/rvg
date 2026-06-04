<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\StoreLedgerTradeRequest;
use App\Models\LedgerTrade;
use App\Models\Squadron;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SquadronLedgerTradeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function store(StoreLedgerTradeRequest $request, Squadron $squadron)
    {
        $this->authorize('manageLedger', $squadron);

        $user = $request->user();
        $this->ledger->createSquadronTrade($user, $squadron, $request->validated());

        return back()->with('success', 'Squadron trade run logged.');
    }

    public function update(StoreLedgerTradeRequest $request, Squadron $squadron, LedgerTrade $trade)
    {
        $this->authorize('manageLedger', $squadron);

        $user = $request->user();
        $this->ledger->updateSquadronTrade($user, $squadron, $trade, $request->validated());

        return back()->with('success', 'Squadron trade run updated.');
    }

    public function destroy(Squadron $squadron, LedgerTrade $trade)
    {
        $this->authorize('manageLedger', $squadron);

        $user = request()->user();
        $this->ledger->deleteSquadronTrade($user, $squadron, $trade);

        return back()->with('success', 'Squadron trade run deleted.');
    }
}
