<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\StoreLedgerShipAssetRequest;
use App\Models\LedgerShipAsset;
use App\Models\Squadron;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SquadronLedgerShipAssetController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function store(StoreLedgerShipAssetRequest $request, Squadron $squadron)
    {
        $this->authorize('manageLedger', $squadron);

        $user = $request->user();
        $this->ledger->createSquadronShipAsset($user, $squadron, $request->validated());

        return back()->with('success', 'Squadron ship asset added.');
    }

    public function update(StoreLedgerShipAssetRequest $request, Squadron $squadron, LedgerShipAsset $ship)
    {
        $this->authorize('manageLedger', $squadron);

        $user = $request->user();
        $this->ledger->updateSquadronShipAsset($user, $squadron, $ship, $request->validated());

        return back()->with('success', 'Squadron ship asset updated.');
    }

    public function destroy(Squadron $squadron, LedgerShipAsset $ship)
    {
        $this->authorize('manageLedger', $squadron);

        $user = request()->user();
        $this->ledger->deleteSquadronShipAsset($user, $squadron, $ship);

        return back()->with('success', 'Squadron ship asset deleted.');
    }
}
