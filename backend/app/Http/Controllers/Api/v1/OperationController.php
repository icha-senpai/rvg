<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Operations\OperationIndexRequest;
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

    public function index(OperationIndexRequest $request)
    {
        $this->authorize('viewAny', Operation::class);

        $now = now();

        $validated = $request->validated();

        $status = (string) ($validated['status'] ?? 'active');
        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 12);

        $allowedStatuses = [
            'active',
            'all',
            'draft',
            'published',
            'in_progress',
            'completed',
            'canceled',
        ];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'active';
        }

        $query = Operation::query()
            ->with(['squadron.leader', 'creator.roles']);

        if ($status === 'active') {
            $query->whereIn('status', ['published', 'in_progress']);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $searchId = null;
            if (preg_match('/^#?(\d+)$/', $search, $matches)) {
                $searchId = (int) $matches[1];
            }

            $query->where(function ($q) use ($search, $searchId) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");

                $q->orWhereHas('squadron', function ($squadronQuery) use ($search) {
                    $squadronQuery->where('name', 'like', "%{$search}%");
                });

                $q->orWhereHas('creator', function ($creatorQuery) use ($search) {
                    $creatorQuery->where('rsi_handle', 'like', "%{$search}%");
                });

                if ($searchId !== null) {
                    $q->orWhere('id', $searchId);
                }
            });
        }

        $operations = $query
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 2 WHEN starts_at >= ? THEN 0 ELSE 1 END', [$now])
            ->orderByRaw('CASE WHEN starts_at >= ? THEN starts_at END ASC', [$now])
            ->orderByRaw('CASE WHEN starts_at < ? THEN starts_at END DESC', [$now])
            ->paginate($perPage)
            ->withQueryString();

        $operations->setCollection(
            $operations->getCollection()->map(
                fn (Operation $op) => OperationPresenter::make($op)->summary()
            )
        );

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
            'status'    => 'success',
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
            $data['reason'] ?? null,
            $data['outcome'] ?? null
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

        $data = $request->validate([
            'outcome' => 'required|in:success,failed',
        ]);

        $updated = $this->service->transition($operation, 'completed', null, $data['outcome']);

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
