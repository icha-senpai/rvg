<?php

namespace App\Http\Controllers\Web;

use App\Application\Operations\OperationShowDataService;
use App\Application\Operations\Presenters\OperationPresenter;
use App\Application\Operations\Presenters\OperationPresenterRelations;
use App\Application\Operations\Queries\OperationQuery;
use App\Application\Squadrons\Presenters\SquadronPresenter;
use App\Http\Controllers\Controller;
use App\Domain\AccessControl\RoleHierarchy;
use App\Domain\Squadrons\SquadronService;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Operation;
use App\Models\OperationTemplate;
use App\Models\Squadron;
use App\Models\User;
use App\Http\Requests\Operations\OperationStoreRequest;
use App\Http\Requests\Operations\OperationUpdateRequest;
use App\Http\Requests\Operations\OperationAfterActionReportUpdateRequest;
use App\Http\Requests\Operations\OperationSettlementUpsertRequest;
use App\Http\Requests\Operations\OperationTemplateStoreRequest;
use App\Http\Requests\Operations\OperationTemplateUpdateRequest;

use App\Domain\Operations\Services\OperationService;
use App\Domain\Operations\Services\OperationTemplateService;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;

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
            'afterActionOperations' => $this->showData->dashboardAfterActionOperations($user),
            'operationSettlementLootOptions' => $this->showData->settlementLootOptions(),
            'verifiedMembers' => $this->showData->verifiedMembers(),
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
        $data = $this->showData->build($operation, $user);

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

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make(OperationPresenterRelations::loadForFull($operation))->full(),
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

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make(OperationPresenterRelations::loadForFull($operation))->full(),
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
            ...$this->showData->editor($operation),
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

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make(OperationPresenterRelations::loadForFull($updated))->full(),
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
     * Update the completed operation after action report and final attendance
     * roster without exposing the full mission editor.
     */
    public function updateAfterActionReport(OperationAfterActionReportUpdateRequest $request, Operation $operation)
    {
        $this->authorize('manageAfterActionReport', $operation);

        $updated = $this->service->updateAfterActionReport(
            $operation,
            $request->validated('after_action_report'),
            $request->validated('attendance_user_ids', []),
            $request->validated('no_show_user_ids', [])
        );

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make(OperationPresenterRelations::loadForFull($updated))->full(),
                ],
            ]);
        }

        return back()->with('success', 'After Action Report updated.');
    }

    public function updateSettlement(OperationSettlementUpsertRequest $request, Operation $operation)
    {
        $this->authorize('manageAfterActionReport', $operation);

        $this->service->upsertSettlementDraft(
            $request->user(),
            $operation,
            $request->validated()
        );

        return back()->with('success', 'Operation settlement draft saved.');
    }

    public function finalizeSettlement(OperationSettlementUpsertRequest $request, Operation $operation)
    {
        $this->authorize('manageAfterActionReport', $operation);

        $this->service->finalizeSettlement(
            $request->user(),
            $operation,
            $request->validated()
        );

        return back()->with('success', 'Operation settlement finalized.');
    }

    public function reopenSettlement(Request $request, Operation $operation)
    {
        $this->authorize('manageAfterActionReport', $operation);

        $this->service->reopenSettlement($request->user(), $operation);

        return back()->with('success', 'Operation settlement reopened.');
    }

    public function exportSettlement(Request $request, Operation $operation)
    {
        $this->authorize('manageAfterActionReport', $operation);

        $settlement = $this->showData->settlementPayload($operation, $request->user(), true, null, false);

        if (! ($settlement['is_finalized'] ?? false)) {
            abort(409, 'Finalize the operation settlement before exporting it.');
        }

        $filename = Str::slug($operation->title ?: "operation-{$operation->id}") . '-settlement.csv';

        return response()->streamDownload(function () use ($operation, $settlement) {
            $output = fopen('php://output', 'w');

            fputcsv($output, [
                'section',
                'operation_id',
                'operation_title',
                'recipient',
                'loot_type',
                'loot_label',
                'quantity',
                'unit_label',
                'amount',
                'currency',
                'notes',
            ]);

            foreach ($settlement['money_rows'] ?? [] as $row) {
                fputcsv($output, [
                    'money',
                    $operation->id,
                    $operation->title,
                    $row['recipient_label'] ?? '',
                    '',
                    '',
                    '',
                    '',
                    $row['amount'] ?? '',
                    'aUEC',
                    $row['notes'] ?? '',
                ]);
            }

            foreach ($settlement['loot_rows'] ?? [] as $row) {
                fputcsv($output, [
                    'loot',
                    $operation->id,
                    $operation->title,
                    $row['recipient_label'] ?? '',
                    $row['source_type'] ?? '',
                    $row['reference_label'] ?? '',
                    $row['quantity'] ?? '',
                    $row['unit_label'] ?? '',
                    '',
                    '',
                    $row['notes'] ?? '',
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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

        $canCreateOperation = $user->can('create', Operation::class);

        $operations = $this->query->forUser($user)
            ->withQueryString();

        return Inertia::render('Operations/OperationsIndex', [
            'operations' => $operations,
            'activeOperation' => $this->resolveActiveOperation($request),
            'editingOperation' => $this->resolveEditingOperation($request),
            'squadrons' => $canCreateOperation
                ? SquadronPresenter::collection($this->squadrons->listAll())
                : [],
            'operationTemplates' => $canCreateOperation
                ? $this->operationTemplatesFor($user)
                : [],
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
            $request->user()
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

        return $this->showData->editor($operation);
    }

    /**
     * Cancel an operation from the web UI and return to the previous page.
     */
    public function destroy(Request $request, Operation $operation)
    {
        $this->authorize('delete', $operation);

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->service->cancel($operation, $data['reason']);

        return back()->with('success', 'Operation canceled.');
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
