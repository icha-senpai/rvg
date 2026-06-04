<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\StoreLedgerShipAssetRequest;
use App\Models\LedgerShipAsset;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OrganizationLedgerShipAssetController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function store(StoreLedgerShipAssetRequest $request)
    {
        $this->authorize('manage-org-ledger');

        $this->ledger->createOrgShipAsset($request->user(), $request->validated());

        return back()->with('success', 'Org ship asset added.');
    }

    public function update(StoreLedgerShipAssetRequest $request, LedgerShipAsset $ship)
    {
        $this->authorize('manage-org-ledger');

        $this->ledger->updateOrgShipAsset($request->user(), $ship, $request->validated());

        return back()->with('success', 'Org ship asset updated.');
    }

    public function destroy(LedgerShipAsset $ship)
    {
        $this->authorize('manage-org-ledger');

        $this->ledger->deleteOrgShipAsset(request()->user(), $ship);

        return back()->with('success', 'Org ship asset deleted.');
    }
}
