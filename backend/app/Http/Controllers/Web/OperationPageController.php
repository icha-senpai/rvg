<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\AccessControl\RoleHierarchy;
use App\Domain\Squadrons\Presenters\SquadronPresenter;
use App\Domain\Squadrons\SquadronService;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Operation;
use App\Models\OperationTemplate;
use App\Models\Squadron;
use App\Models\User;
use App\Http\Requests\Operations\OperationStoreRequest;
use App\Http\Requests\Operations\OperationUpdateRequest;
use App\Http\Requests\Operations\OperationTemplateStoreRequest;
use App\Http\Requests\Operations\OperationTemplateUpdateRequest;

use App\Domain\Operations\Services\OperationService;
use App\Domain\Operations\Services\OperationMediaService;
use App\Domain\Operations\Services\OperationShowDataService;
use App\Domain\Operations\Services\OperationTemplateService;
use App\Domain\Operations\Presenters\OperationPresenter;
use App\Domain\Operations\Queries\OperationQuery;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Web controller for the operations dashboard, operation detail screens, editor
 * flows, and template management actions.
 *
 * The controller mainly coordinates authorization, request/response transport,
 * and delegation into domain services that hold the business logic.
 */
class OperationPageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OperationService $service,
        protected OperationQuery $query,
        protected OperationMediaService $operationMedia,
        protected OperationShowDataService $showData,
        protected OperationTemplateService $templates,
        protected SquadronService $squadrons
    ) {}

    /**
     * Render the operations dashboard for lieutenant-and-above users.
     *
     * The query returns both the summarized operations list and the normalized
     * filter state so the dashboard can preserve the current search UI.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        // The dashboard uses role hierarchy rather than a single permission so the
        // same lieutenant+ rule can stay consistent across the UI.
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
            'squadrons' => SquadronPresenter::collection($this->squadrons->listAll()),
            'operationTemplates' => $this->operationTemplatesFor($user),
            'activeOperation' => $this->resolveActiveOperation($request),
            'editingOperation' => $this->resolveEditingOperation($request),
        ]);
    }

    /**
     * Render the dedicated operation detail page.
     */
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

    /**
     * Render the editor for creating a global operation.
     */
    public function createGlobal(Request $request)
    {
        $this->authorize('create', Operation::class);

        return Inertia::render('Operations/Components/MissionEditorForm', [
            'mission'    => null,
            'squadronId' => null,
            'squadrons' => SquadronPresenter::collection($this->squadrons->listAll()),
            'operationTemplates' => $this->operationTemplatesFor($request->user()),
        ]);
    }

    /**
     * Create a new global operation from the web editor flow.
     *
     * The response shape adapts to JSON callers and Inertia form flows without
     * changing the underlying domain logic.
     */
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

        if ($request->boolean('stay_on_page')) {
            return back()
                ->with('success', 'Operation created.')
                ->with('operation', [
                    'event' => 'created',
                    'id' => $operation->id,
                ]);
        }

        return Inertia::location(route('operations.show', $operation->id));
    }

    /**
     * Render the editor for creating a squadron-scoped operation.
     */
    public function create(Request $request, $squadronId)
    {
        $this->authorize('create', [Operation::class, Squadron::findOrFail((int) $squadronId)]);

        return Inertia::render('Operations/Components/MissionEditorForm', [
            'mission'    => null,
            'squadronId' => (int) $squadronId,
            'squadrons' => SquadronPresenter::collection($this->squadrons->listAll()),
            'operationTemplates' => $this->operationTemplatesFor($request->user()),
        ]);
    }

    /**
     * Create a new operation attached to a specific squadron.
     */
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

        if ($request->boolean('stay_on_page')) {
            return back()
                ->with('success', 'Operation created.')
                ->with('operation', [
                    'event' => 'created',
                    'id' => $operation->id,
                ]);
        }

        return Inertia::location(route('operations.show', $operation->id));
    }

    /**
     * Render the editor for updating an existing operation.
     */
    public function edit(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        return Inertia::render('Operations/Components/MissionEditorForm', [
            'mission'    => OperationPresenter::make($operation)->form(),
            'squadronId' => $operation->squadron_id,
            'squadrons' => SquadronPresenter::collection($this->squadrons->listAll()),
            'operationTemplates' => $this->operationTemplatesFor($request->user()),
        ]);
    }

    /**
     * Create a reusable operation template for the current user or squadron scope.
     */
    public function storeTemplate(OperationTemplateStoreRequest $request)
    {
        $user = $request->user();
        $scope = (string) $request->validated('scope');
        $squadronId = $request->validated('squadron_id');

        $this->authorize('create', [OperationTemplate::class, $scope, $squadronId]);

        $template = $this->templates->create(
            $user,
            $scope,
            $scope === OperationTemplate::SCOPE_SQUADRON ? (int) $squadronId : null,
            $request->validated('name'),
            $request->validated('payload')
        );

        return back()
            ->with('operationTemplate', [
                'event' => 'created',
                'id' => $template->id,
            ]);
    }

    /**
     * Update a saved operation template.
     */
    public function updateTemplate(OperationTemplateUpdateRequest $request, OperationTemplate $template)
    {
        $this->authorize('update', $template);

        $this->templates->update($template, $request->validated());

        return back()
            ->with('operationTemplate', [
                'event' => 'updated',
                'id' => $template->id,
            ]);
    }

    /**
     * Delete an operation template and emit a small UI event payload.
     */
    public function destroyTemplate(Request $request, OperationTemplate $template)
    {
        $this->authorize('delete', $template);

        $templateId = $template->id;
        $template->delete();

        return back()
            ->with('operationTemplate', [
                'event' => 'deleted',
                'id' => $templateId,
            ]);
    }

    /**
     * Update an existing operation from the web editor flow.
     */
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

        if ($request->boolean('stay_on_page')) {
            return back()
                ->with('success', 'Operation updated.')
                ->with('operation', [
                    'event' => 'updated',
                    'id' => $updated->id,
                ]);
        }

        return redirect()
            ->route('operations.show', $updated->id)
            ->with('success', 'Operation updated.');
    }

    /**
     * Render the member-facing operation list that shows only operations visible
     * to the current user.
     */
    public function memberIndex(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('operations.index');
        }

        $operations = $this->query->forUser($user)
            ->withQueryString();

        return Inertia::render('Operations/OperationsIndex', [
            'operations' => $operations,
            'activeOperation' => $this->resolveActiveOperation($request),
        ]);
    }

    /**
     * Resolve the operation requested in the dashboard query string and return the
     * fully prepared show payload for the side panel.
     */
    protected function resolveActiveOperation(Request $request): ?array
    {
        $operationId = $request->query('operation');

        if (! $operationId || ! is_numeric($operationId)) {
            return null;
        }

        $operation = Operation::findOrFail((int) $operationId);
        $this->authorize('view', $operation);

        return $this->showData->build(
            $operation,
            $request->user()?->getAuthIdentifier()
        );
    }

    /**
     * Resolve the operation currently being edited through the dashboard query
     * string and return the editor payload.
     */
    protected function resolveEditingOperation(Request $request): ?array
    {
        $operationId = $request->query('edit');

        if (! $operationId || ! is_numeric($operationId)) {
            return null;
        }

        $operation = Operation::findOrFail((int) $operationId);
        $this->authorize('update', $operation);

        return [
            'mission' => OperationPresenter::make($operation)->form(),
            'squadronId' => $operation->squadron_id,
        ];
    }

    /**
     * Cancel an operation from the web UI and return to the previous page.
     */
    public function destroy(Operation $operation)
    {
        $this->authorize('delete', $operation);

        $this->service->cancel($operation);

        return back()->with('success', 'Operation canceled.');
    }

    /**
     * Sync the optional operation image media referenced by the current request.
     *
     * Leaving the field out means "keep the current image". Sending an explicit
     * empty value lets the media service detach the current image.
     */
    private function attachMediaIfProvided(Request $request, Operation $operation): void
    {
        if (! $request->has('media_id')) {
            return;
        }

        $this->operationMedia->syncOperationImage($operation, $request->input('media_id'));
    }

    /**
     * Return the operation templates visible to the current user.
     */
    protected function operationTemplatesFor(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $this->authorize('viewAny', OperationTemplate::class);

        return $this->templates->listVisibleFor($user);
    }
}
