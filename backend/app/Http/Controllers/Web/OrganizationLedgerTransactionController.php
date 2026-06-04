<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\ReverseLedgerTransferRequest;
use App\Http\Requests\Ledger\StoreLedgerTransferRequest;
use App\Http\Requests\Ledger\StoreLedgerTransactionRequest;
use App\Models\LedgerTransferRequest;
use App\Models\LedgerTransaction;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OrganizationLedgerTransactionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function store(StoreLedgerTransactionRequest $request)
    {
        $this->authorize('manage-org-ledger');

        $this->ledger->createOrgTransaction($request->user(), $request->validated());

        return back()->with('success', 'Org ledger transaction saved.');
    }

    public function transfer(StoreLedgerTransferRequest $request)
    {
        $this->authorize('manage-org-ledger');

        $result = $this->ledger->transferFundsFromOrganization($request->user(), $request->validated());

        return back()->with('success', $result['status'] === 'pending'
            ? 'Transfer request sent for approval.'
            : 'Funds transferred.');
    }

    public function reverseTransfer(ReverseLedgerTransferRequest $request, LedgerTransferRequest $transferRequest)
    {
        $this->authorize('manage-org-ledger');

        $this->ledger->reverseFundTransferForOrganization($request->user(), $transferRequest, $request->validated());

        return back()->with('success', 'Transfer reversed.');
    }

    public function update(StoreLedgerTransactionRequest $request, LedgerTransaction $transaction)
    {
        $this->authorize('manage-org-ledger');

        $this->ledger->updateOrgTransaction($request->user(), $transaction, $request->validated());

        return back()->with('success', 'Org ledger transaction updated.');
    }

    public function destroy(LedgerTransaction $transaction)
    {
        $this->authorize('manage-org-ledger');

        $this->ledger->deleteOrgTransaction(request()->user(), $transaction);

        return back()->with('success', 'Org ledger transaction deleted.');
    }
}
