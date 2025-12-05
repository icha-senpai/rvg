<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Operation;
use App\Models\Squadron;
use App\Domain\Operations\OperationService;
use App\Domain\Operations\Presenters\OperationPresenter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OperationPageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OperationService $service
    ) {}

    public function index()
    {
        $operations = Operation::orderByDesc('starts_at')
            ->with(['squadron', 'creator'])
            ->get()
            ->map(fn ($op) => OperationPresenter::make($op)->summary());

        return Inertia::render('Operations/MissionsIndex', [
            'operations' => $operations,
        ]);
    }

    public function show(Operation $operation)
    {
        $this->authorize('view', $operation);

        $operation = $this->service->loadGraph($operation);

        return Inertia::render('Operations/MissionShow', [
            'operation' => OperationPresenter::make($operation)->full(),
            'authUser'  => auth()->user(),
        ]);
    }

    public function create($squadronId)
    {
        return Inertia::render('Operations/MissionEditor', [
            'mission'    => null,
            'squadronId' => (int) $squadronId,
        ]);
    }

    public function store(Request $request, Squadron $squadron)
    {
        $this->authorize('create', [Operation::class, $squadron]);

        $operation = $this->service->create($request->all(), $squadron);

        return redirect()
            ->route('operations.show', $operation->id);
    }

    public function edit(Operation $operation)
    {
        $this->authorize('update', $operation);

        return Inertia::render('Operations/MissionEditor', [
            'mission'    => OperationPresenter::make($operation)->form(),
            'squadronId' => $operation->squadron_id,
        ]);
    }

    public function update(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $updated = $this->service->update($operation, $request->all());

        return redirect()
            ->route('operations.show', $updated->id)
            ->with('success', 'Operation updated.');
    }
}
