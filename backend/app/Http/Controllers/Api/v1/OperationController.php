<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Operations\OperationStoreRequest;
use App\Http\Requests\Operations\OperationUpdateRequest;
use App\Http\Requests\Operations\OperationStatusUpdateRequest;
use App\Models\Operation;
use App\Models\Squadron;
use App\Domain\Operations\Services\OperationService;
use App\Domain\Operations\Presenters\OperationPresenter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OperationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OperationService $service
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Operation::class);

        $operations = Operation::with(['squadron', 'creator'])
            ->orderBy('starts_at')
            ->get()
            ->map(fn ($op) => OperationPresenter::make($op)->summary());

        return response()->json($operations);
    }

    public function show(Operation $operation)
    {
        $this->authorize('view', $operation);

        $operation = $this->service->loadGraph($operation);

        return response()->json(
            OperationPresenter::make($operation)->full()
        );
    }

    public function store(OperationStoreRequest $request, Squadron $squadron)
    {
        $this->authorize('create', [Operation::class, $squadron]);

        $operation = $this->service->create($request->validated(), $squadron);

        return response()->json(
            OperationPresenter::make($operation)->full(),
            201
        );
    }

    public function storeGlobal(OperationStoreRequest $request)
    {
        $this->authorize('create', Operation::class);

        $operation = $this->service->create($request->validated(), null);

        return response()->json(
            OperationPresenter::make($operation)->full(),
            201
        );
    }

    public function update(OperationUpdateRequest $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $updated = $this->service->update($operation, $request->validated());

        return response()->json(
            OperationPresenter::make($updated)->full()
        );
    }

    public function destroy(Operation $operation)
    {
        $this->authorize('delete', $operation);

        $deleted = $this->service->cancel($operation);

        return response()->json([
            'message'   => 'Operation canceled',
            'operation' => OperationPresenter::make($deleted)->summary(),
        ]);
    }

    public function updateStatus(OperationStatusUpdateRequest $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $data = $request->validated();

        $updated = $this->service->transition(
            $operation,
            $data['status'],
            $data['reason'] ?? null
        );

        return response()->json(
            OperationPresenter::make($updated)->full()
        );
    }

    public function start(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $updated = $this->service->transition($operation, 'in_progress');

        return response()->json(
            OperationPresenter::make($updated)->full()
        );
    }

    public function complete(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $updated = $this->service->transition($operation, 'completed');

        return response()->json(
            OperationPresenter::make($updated)->full()
        );
    }

    public function cancel(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $updated = $this->service->transition(
            $operation,
            'canceled',
            $data['reason'] ?? null
        );

        return response()->json(
            OperationPresenter::make($updated)->full()
        );
    }
    
}
