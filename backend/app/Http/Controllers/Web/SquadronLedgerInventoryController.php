<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\ApproveLedgerTransferRequest;
use App\Http\Requests\Ledger\RejectLedgerTransferRequest;
use App\Http\Requests\Ledger\StoreLedgerInventoryItemRequest;
use App\Http\Requests\Ledger\StoreLedgerInventoryTransferRequest;
use App\Models\LedgerInventoryItem;
use App\Models\LedgerTransferRequest;
use App\Models\Squadron;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SquadronLedgerInventoryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function store(StoreLedgerInventoryItemRequest $request, Squadron $squadron)
    {
        $this->authorize('manageLedger', $squadron);

        $user = $request->user();
        $this->ledger->createSquadronInventoryItem($user, $squadron, $request->validated());

        return back()->with('success', 'Squadron inventory item added.');
    }

    public function transfer(StoreLedgerInventoryTransferRequest $request, Squadron $squadron)
    {
        $this->authorize('manageLedger', $squadron);

        $user = $request->user();
        $result = $this->ledger->transferInventoryFromSquadron($user, $squadron, $request->validated());

        return back()->with('success', $result['status'] === 'pending'
            ? 'Inventory transfer request sent for approval.'
            : 'Inventory transferred.');
    }

    public function approveTransfer(ApproveLedgerTransferRequest $request, Squadron $squadron, LedgerTransferRequest $transferRequest)
    {
        $this->authorize('manageLedger', $squadron);

        $this->ledger->approvePendingInventoryTransferForSquadron($request->user(), $squadron, $transferRequest);

        return back()->with('success', 'Inventory transfer approved.');
    }

    public function rejectTransfer(RejectLedgerTransferRequest $request, Squadron $squadron, LedgerTransferRequest $transferRequest)
    {
        $this->authorize('manageLedger', $squadron);

        $this->ledger->rejectPendingInventoryTransferForSquadron($request->user(), $squadron, $transferRequest, $request->validated());

        return back()->with('success', 'Inventory transfer rejected.');
    }

    public function update(StoreLedgerInventoryItemRequest $request, Squadron $squadron, LedgerInventoryItem $inventoryItem)
    {
        $this->authorize('manageLedger', $squadron);

        $user = $request->user();
        $this->ledger->updateSquadronInventoryItem($user, $squadron, $inventoryItem, $request->validated());

        return back()->with('success', 'Squadron inventory item updated.');
    }

    public function destroy(Squadron $squadron, LedgerInventoryItem $inventoryItem)
    {
        $this->authorize('manageLedger', $squadron);

        $user = request()->user();
        $this->ledger->deleteSquadronInventoryItem($user, $squadron, $inventoryItem);

        return back()->with('success', 'Squadron inventory item deleted.');
    }
}
