<?php

namespace App\Http\Controllers\Api\v1;

use App\Application\Operations\Presenters\OperationPresenter;
use App\Application\Operations\Queries\OperationQuery;
use App\Domain\Operations\Enums\CompletionOutcome;
use App\Domain\Operations\Enums\OperationStatus;
use App\Domain\Operations\Services\OperationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\OperationIndexRequest;
use App\Http\Requests\Operations\OperationStatusUpdateRequest;
use App\Http\Requests\Operations\OperationStoreRequest;
use App\Http\Requests\Operations\OperationUpdateRequest;
use App\Models\Operation;
use App\Models\Squadron;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * JSON API controller for operation listing, detail, lifecycle, and mutation
 * actions.
 *
 * The controller stays thin: requests authorize and validate, domain services do
 * the real work, and presenters shape the response payloads.
 */
class OperationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OperationService $service,
        protected OperationQuery $query,
    ) {}

    /**
     * Return a paginated list of operations matching the request query parameters.
     */
    public function index(OperationIndexRequest $request)
    {
        $this->authorize('viewAny', Operation::class);

        $validated = $request->validated();

        $status = (string) ($validated['status'] ?? 'active');
        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 12);

        $operations = $this->query->summaryList($status, $search, $perPage);

        return response()->json($operations);
    }

    /**
     * Return the full presented payload for a single operation.
     */
    public function show(Operation $operation)
    {
        $this->authorize('view', $operation);

        $operation = $this->service->loadGraph($operation);

        return response()->json(
            OperationPresenter::make($operation)->full()
        );
    }

    /**
     * Create a squadron-scoped operation through the API.
     */
    public function store(OperationStoreRequest $request, Squadron $squadron)
    {
        $this->authorize('create', [Operation::class, $squadron]);

        $operation = $this->service->create($request->validated(), $squadron);

        return response()->json(
            OperationPresenter::make($operation)->full(),
            201
        );
    }

    /**
     * Create a global operation that does not belong to a squadron.
     */
    public function storeGlobal(OperationStoreRequest $request)
    {
        $this->authorize('create', Operation::class);

        $operation = $this->service->create($request->validated(), null);

        return response()->json(
            OperationPresenter::make($operation)->full(),
            201
        );
    }

    /**
     * Update an existing operation and return the full refreshed payload.
     */
    public function update(OperationUpdateRequest $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $updated = $this->service->update($operation, $request->validated());

        return response()->json(
            OperationPresenter::make($updated)->full()
        );
    }

    /**
     * Cancel the operation through the domain cancel flow and return a summary
     * payload for list refreshes.
     */
    public function destroy(Operation $operation)
    {
        $this->authorize('delete', $operation);

        $deleted = $this->service->cancel($operation);

        return response()->json([
            'status' => 'success',
            'message' => 'Operation canceled',
            'operation' => OperationPresenter::make($deleted)->summary(),
        ]);
    }

    /**
     * Transition the operation to an explicitly requested status.
     */
    public function updateStatus(OperationStatusUpdateRequest $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $data = $request->validated();

        $updated = $this->service->transition(
            $operation,
            $data['status'],
            $data['reason'] ?? null,
            $data['outcome'] ?? null
        );

        return response()->json(
            OperationPresenter::make($updated)->full()
        );
    }

    /**
     * Convenience endpoint for moving an operation into the in-progress state.
     */
    public function start(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $updated = $this->service->transition($operation, OperationStatus::InProgress->value);

        return response()->json(
            OperationPresenter::make($updated)->full()
        );
    }

    /**
     * Complete an operation and require the completion outcome in the payload.
     */
    public function complete(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $data = $request->validate([
            'outcome' => 'required|in:' . implode(',', CompletionOutcome::values()),
        ]);

        $updated = $this->service->transition(
            $operation,
            OperationStatus::Completed->value,
            null,
            $data['outcome']
        );

        return response()->json(
            OperationPresenter::make($updated)->full()
        );
    }

    /**
     * Cancel an operation with an optional human-readable reason.
     */
    public function cancel(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $updated = $this->service->transition(
            $operation,
            OperationStatus::Canceled->value,
            $data['reason'] ?? null
        );

        return response()->json(
            OperationPresenter::make($updated)->full()
        );
    }
}
