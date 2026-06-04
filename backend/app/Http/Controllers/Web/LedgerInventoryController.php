<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\StoreLedgerInventoryItemRequest;
use App\Http\Requests\Ledger\StoreLedgerInventoryTransferRequest;
use App\Models\LedgerInventoryItem;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class LedgerInventoryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function store(StoreLedgerInventoryItemRequest $request)
    {
        $this->authorize('edit-own-ledger');

        $user = $request->user();
        $this->ledger->createInventoryItem($user, $user, $request->validated());

        return back()->with('success', 'Inventory item added.');
    }

    public function transfer(StoreLedgerInventoryTransferRequest $request)
    {
        $this->authorize('edit-own-ledger');

        $user = $request->user();
        $this->ledger->transferInventoryFromPersonal($user, $request->validated());

        return back()->with('success', 'Inventory transferred.');
    }

    public function update(StoreLedgerInventoryItemRequest $request, LedgerInventoryItem $inventoryItem)
    {
        $this->authorize('edit-own-ledger');

        $user = $request->user();
        $this->ledger->updateInventoryItem($user, $user, $inventoryItem, $request->validated());

        return back()->with('success', 'Inventory item updated.');
    }

    public function destroy(LedgerInventoryItem $inventoryItem)
    {
        $this->authorize('delete-own-ledger');

        $user = request()->user();
        $this->ledger->deleteInventoryItem($user, $user, $inventoryItem);

        return back()->with('success', 'Inventory item deleted.');
    }
}
