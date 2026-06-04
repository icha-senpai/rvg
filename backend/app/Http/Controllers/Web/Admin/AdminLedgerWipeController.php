<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\RenameWipeCycleRequest;
use App\Http\Requests\Ledger\StoreWipeCycleRequest;
use App\Models\WipeCycle;
use App\Services\LedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AdminLedgerWipeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LedgerService $ledger,
    ) {}

    public function store(StoreWipeCycleRequest $request)
    {
        $this->authorize('access-admin-panel');

        $this->ledger->createWipeCycle($request->user(), $request->validated());

        return back()->with('success', 'New cycle created and set current.');
    }

    public function activate(WipeCycle $wipeCycle)
    {
        $this->authorize('access-admin-panel');

        $this->ledger->setCurrentWipeCycle(request()->user(), $wipeCycle);

        return back()->with('success', 'Cycle set as current.');
    }

    public function close(WipeCycle $wipeCycle)
    {
        $this->authorize('access-admin-panel');

        $this->ledger->closeWipeCycle(request()->user(), $wipeCycle);

        return back()->with('success', 'Cycle closed.');
    }

    public function rename(RenameWipeCycleRequest $request, WipeCycle $wipeCycle)
    {
        $this->authorize('access-admin-panel');

        $this->ledger->updateCurrentWipeCycle($request->user(), $wipeCycle, $request->validated());

        return back()->with('success', 'Current cycle updated.');
    }
}
