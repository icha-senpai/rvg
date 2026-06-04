<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\StoreLedgerTradeRequest;
use App\Models\LedgerTrade;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class LedgerTradeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function store(StoreLedgerTradeRequest $request)
    {
        $this->authorize('edit-own-ledger');

        $user = $request->user();
        $this->ledger->createTrade($user, $user, $request->validated());

        return back()->with('success', 'Trade run logged.');
    }

    public function update(StoreLedgerTradeRequest $request, LedgerTrade $trade)
    {
        $this->authorize('edit-own-ledger');

        $user = $request->user();
        $this->ledger->updateTrade($user, $user, $trade, $request->validated());

        return back()->with('success', 'Trade run updated.');
    }

    public function destroy(LedgerTrade $trade)
    {
        $this->authorize('delete-own-ledger');

        $user = request()->user();
        $this->ledger->deleteTrade($user, $user, $trade);

        return back()->with('success', 'Trade run deleted.');
    }
}
