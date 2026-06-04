<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\StoreLedgerInventoryItemRequest;
use App\Http\Requests\Ledger\StoreLedgerInventoryTransferRequest;
use App\Models\LedgerInventoryItem;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OrganizationLedgerInventoryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function store(StoreLedgerInventoryItemRequest $request)
    {
        $this->authorize('manage-org-ledger');

        $this->ledger->createOrgInventoryItem($request->user(), $request->validated());

        return back()->with('success', 'Org inventory item added.');
    }

    public function transfer(StoreLedgerInventoryTransferRequest $request)
    {
        $this->authorize('manage-org-ledger');

        $this->ledger->transferInventoryFromOrganization($request->user(), $request->validated());

        return back()->with('success', 'Inventory transferred.');
    }

    public function update(StoreLedgerInventoryItemRequest $request, LedgerInventoryItem $inventoryItem)
    {
        $this->authorize('manage-org-ledger');

        $this->ledger->updateOrgInventoryItem($request->user(), $inventoryItem, $request->validated());

        return back()->with('success', 'Org inventory item updated.');
    }

    public function destroy(LedgerInventoryItem $inventoryItem)
    {
        $this->authorize('manage-org-ledger');

        $this->ledger->deleteOrgInventoryItem(request()->user(), $inventoryItem);

        return back()->with('success', 'Org inventory item deleted.');
    }
}
