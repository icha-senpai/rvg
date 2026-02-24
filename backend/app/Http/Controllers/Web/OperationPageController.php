<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\AccessControl\RoleHierarchy;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Operation;
use App\Models\Squadron;
use App\Http\Requests\Operations\OperationStoreRequest;
use App\Http\Requests\Operations\OperationUpdateRequest;

// Services & Presenters
use App\Domain\Operations\Services\OperationService;
use App\Domain\Operations\Services\OperationMediaService;
use App\Domain\Operations\Services\OperationShowDataService;
use App\Domain\Operations\Presenters\OperationPresenter;
use App\Domain\Operations\Queries\OperationQuery;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OperationPageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OperationService    $service,
        protected OperationQuery      $query,
        protected OperationMediaService $operationMedia,
        protected OperationShowDataService $showData
    ) {}

    /* ============================================================
     | INDEX (ALL OPS)
     * ============================================================ */
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $user->loadMissing('roles:id,slug');

        if (! RoleHierarchy::userAtLeast($user, 'lieutenant')) {
            abort(403);
        }

        [$operations, $filters] = $this->query->dashboardList(
            (string) $request->query('status', 'active'),
            (string) $request->query('search', ''),
            12
        );

        return Inertia::render('Operations/OperationDashboard', [
            'operations' => $operations,
            'filters' => $filters,
        ]);
    }

    /* ============================================================
     | SHOW SINGLE OPERATION
     * ============================================================ */
    public function show(Request $request, Operation $operation)
    {
        $this->authorize('view', $operation);

        $user = $request->user();
        $data = $this->showData->build($operation, $user?->getAuthIdentifier());

        return Inertia::render('Operations/MissionShow', [
            ...$data,
            'authUser' => $user,
        ]);
    }

    /* ============================================================
     | CREATE
     * ============================================================ */
    public function createGlobal()
    {
        $this->authorize('create', Operation::class);

        return Inertia::render('Operations/Components/MissionEditorForm', [
            'mission'    => null,
            'squadronId' => null,
        ]);
    }

    public function storeGlobal(OperationStoreRequest $request)
    {
        $this->authorize('create', Operation::class);

        $operation = $this->service->create($request->validated(), null);

        $this->attachMediaIfProvided($request, $operation);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make($operation)->full(),
                ],
            ], 201);
        }

        return Inertia::location(route('operations.show', $operation->id));
    }

    public function create($squadronId)
    {
        $this->authorize('create', [Operation::class, Squadron::findOrFail((int) $squadronId)]);

        return Inertia::render('Operations/Components/MissionEditorForm', [
            'mission'    => null,
            'squadronId' => (int) $squadronId,
        ]);
    }

    public function store(OperationStoreRequest $request, Squadron $squadron)
    {
        $this->authorize('create', [Operation::class, $squadron]);

        $operation = $this->service->create($request->validated(), $squadron);

        $this->attachMediaIfProvided($request, $operation);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make($operation)->full(),
                ],
            ], 201);
        }

        return Inertia::location(route('operations.show', $operation->id));
    }

    /* ============================================================
     | EDIT / UPDATE
     * ============================================================ */
    public function edit(Operation $operation)
    {
        $this->authorize('update', $operation);

        return Inertia::render('Operations/Components/MissionEditorForm', [
            'mission'    => OperationPresenter::make($operation)->form(),
            'squadronId' => $operation->squadron_id,
        ]);
    }

    public function editData(Request $request, Operation $operation)
    {
        $user = $request->user();

        if (! $user || $user->cannot('update', $operation)) {
            abort(404);
        }

        return response()->json([
            'status' => 'ok',
            'payload' => [
                'mission' => OperationPresenter::make($operation)->form(),
                'squadronId' => $operation->squadron_id,
            ],
        ]);
    }

    public function update(OperationUpdateRequest $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $updated = $this->service->update($operation, $request->validated());

        $this->attachMediaIfProvided($request, $updated);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make($updated)->full(),
                ],
            ]);
        }

        return redirect()
            ->route('operations.show', $updated->id)
            ->with('success', 'Operation updated.');
    }

    /* ============================================================
     | MEMBER INDEX (visible ops)
     * ============================================================ */
    public function memberIndex(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('operations.index');
        }

        $operations = $this->query->forUser($user)
            ->withQueryString();

        return Inertia::render('Operations/OperationsIndex', [
            'operations' => $operations,
        ]);
    }

    public function showData(Request $request, Operation $operation)
    {
        $user = $request->user();

        if (! $user || $user->cannot('view', $operation)) {
            abort(404);
        }

        return response()->json(
            $this->showData->build(
                $operation,
                $request->user()?->getAuthIdentifier()
            )
        );
    }

    /* ============================================================
     | DELETE
     * ============================================================ */
    public function destroy(Operation $operation)
    {
        $this->authorize('delete', $operation);

        $this->service->cancel($operation);

        return back()->with('success', 'Operation canceled.');
    }

    /* ============================================================
     | MEDIA HELPER
     * ============================================================ */

    /**
     * If the request includes a media_id, attach that media to the operation.
     * If media_id is explicitly null/0, detach any current operation image.
     */
    private function attachMediaIfProvided(Request $request, Operation $operation): void
    {
        if (! $request->has('media_id')) {
            return;
        }

        $this->operationMedia->syncOperationImage($operation, $request->input('media_id'));
    }
}
