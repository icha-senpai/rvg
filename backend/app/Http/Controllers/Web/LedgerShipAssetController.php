<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\StoreLedgerShipAssetRequest;
use App\Models\LedgerShipAsset;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class LedgerShipAssetController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function store(StoreLedgerShipAssetRequest $request)
    {
        $this->authorize('edit-own-ledger');

        $user = $request->user();
        $this->ledger->createShipAsset($user, $user, $request->validated());

        return back()->with('success', 'Ship asset added.');
    }

    public function update(StoreLedgerShipAssetRequest $request, LedgerShipAsset $ship)
    {
        $this->authorize('edit-own-ledger');

        $user = $request->user();
        $this->ledger->updateShipAsset($user, $user, $ship, $request->validated());

        return back()->with('success', 'Ship asset updated.');
    }

    public function destroy(LedgerShipAsset $ship)
    {
        $this->authorize('delete-own-ledger');

        $user = request()->user();
        $this->ledger->deleteShipAsset($user, $user, $ship);

        return back()->with('success', 'Ship asset deleted.');
    }
}
