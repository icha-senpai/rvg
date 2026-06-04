<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\StoreLedgerTransferRequest;
use App\Http\Requests\Ledger\StoreLedgerTransactionRequest;
use App\Models\LedgerTransaction;
use App\Models\Squadron;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SquadronLedgerTransactionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function store(StoreLedgerTransactionRequest $request, Squadron $squadron)
    {
        $this->authorize('manageLedger', $squadron);

        $user = $request->user();
        $this->ledger->createSquadronTransaction($user, $squadron, $request->validated());

        return back()->with('success', 'Squadron ledger transaction saved.');
    }

    public function transfer(StoreLedgerTransferRequest $request, Squadron $squadron)
    {
        $this->authorize('manageLedger', $squadron);

        $user = $request->user();
        $this->ledger->transferFundsFromSquadron($user, $squadron, $request->validated());

        return back()->with('success', 'Funds transferred.');
    }

    public function update(StoreLedgerTransactionRequest $request, Squadron $squadron, LedgerTransaction $transaction)
    {
        $this->authorize('manageLedger', $squadron);

        $user = $request->user();
        $this->ledger->updateSquadronTransaction($user, $squadron, $transaction, $request->validated());

        return back()->with('success', 'Squadron ledger transaction updated.');
    }

    public function destroy(Squadron $squadron, LedgerTransaction $transaction)
    {
        $this->authorize('manageLedger', $squadron);

        $user = request()->user();
        $this->ledger->deleteSquadronTransaction($user, $squadron, $transaction);

        return back()->with('success', 'Squadron ledger transaction deleted.');
    }
}
