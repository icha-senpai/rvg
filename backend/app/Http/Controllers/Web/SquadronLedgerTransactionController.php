<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\ApproveLedgerTransferRequest;
use App\Http\Requests\Ledger\RejectLedgerTransferRequest;
use App\Http\Requests\Ledger\ReverseLedgerTransferRequest;
use App\Http\Requests\Ledger\StoreLedgerTransferRequest;
use App\Http\Requests\Ledger\StoreLedgerTransactionRequest;
use App\Models\LedgerTransferRequest;
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
        $result = $this->ledger->transferFundsFromSquadron($user, $squadron, $request->validated());

        return back()->with('success', $result['status'] === 'pending'
            ? 'Transfer request sent for approval.'
            : 'Funds transferred.');
    }

    public function approveTransfer(ApproveLedgerTransferRequest $request, Squadron $squadron, LedgerTransferRequest $transferRequest)
    {
        $this->authorize('manageLedger', $squadron);

        $this->ledger->approvePendingFundTransferForSquadron($request->user(), $squadron, $transferRequest);

        return back()->with('success', 'Transfer approved.');
    }

    public function rejectTransfer(RejectLedgerTransferRequest $request, Squadron $squadron, LedgerTransferRequest $transferRequest)
    {
        $this->authorize('manageLedger', $squadron);

        $this->ledger->rejectPendingFundTransferForSquadron($request->user(), $squadron, $transferRequest, $request->validated());

        return back()->with('success', 'Transfer rejected.');
    }

    public function reverseTransfer(ReverseLedgerTransferRequest $request, Squadron $squadron, LedgerTransferRequest $transferRequest)
    {
        $this->authorize('manageLedger', $squadron);

        $this->ledger->reverseFundTransferForSquadron($request->user(), $squadron, $transferRequest, $request->validated());

        return back()->with('success', 'Transfer reversed.');
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
