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
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class LedgerTransactionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function store(StoreLedgerTransactionRequest $request)
    {
        $this->authorize('edit-own-ledger');

        $user = $request->user();
        $this->ledger->createTransaction($user, $user, $request->validated());

        return back()->with('success', 'Ledger transaction saved.');
    }

    public function transfer(StoreLedgerTransferRequest $request)
    {
        $this->authorize('edit-own-ledger');

        $user = $request->user();
        $result = $this->ledger->transferFundsFromPersonal($user, $request->validated());

        return back()->with('success', $result['status'] === 'pending'
            ? 'Transfer request sent for approval.'
            : 'Funds transferred.');
    }

    public function approveTransfer(ApproveLedgerTransferRequest $request, LedgerTransferRequest $transferRequest)
    {
        $this->authorize('edit-own-ledger');

        $this->ledger->approvePendingFundTransferForPersonal($request->user(), $transferRequest);

        return back()->with('success', 'Transfer approved.');
    }

    public function rejectTransfer(RejectLedgerTransferRequest $request, LedgerTransferRequest $transferRequest)
    {
        $this->authorize('edit-own-ledger');

        $this->ledger->rejectPendingFundTransferForPersonal($request->user(), $transferRequest, $request->validated());

        return back()->with('success', 'Transfer rejected.');
    }

    public function reverseTransfer(ReverseLedgerTransferRequest $request, LedgerTransferRequest $transferRequest)
    {
        $this->authorize('edit-own-ledger');

        $this->ledger->reverseFundTransferForPersonal($request->user(), $transferRequest, $request->validated());

        return back()->with('success', 'Transfer reversed.');
    }

    public function update(StoreLedgerTransactionRequest $request, LedgerTransaction $transaction)
    {
        $this->authorize('edit-own-ledger');

        $user = $request->user();
        $this->ledger->updateTransaction($user, $user, $transaction, $request->validated());

        return back()->with('success', 'Ledger transaction updated.');
    }

    public function destroy(LedgerTransaction $transaction)
    {
        $this->authorize('delete-own-ledger');

        $user = request()->user();
        $this->ledger->deleteTransaction($user, $user, $transaction);

        return back()->with('success', 'Ledger transaction deleted.');
    }
}
