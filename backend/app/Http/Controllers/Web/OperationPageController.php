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
        protected OperationShowDataService $showData,
        protected SquadronService $squadrons
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
            'squadrons' => SquadronPresenter::collection($this->squadrons->listAll()),
            'operationTemplates' => $this->operationTemplatesFor($user),
            'activeOperation' => $this->resolveActiveOperation($request),
            'editingOperation' => $this->resolveEditingOperation($request),
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

    /* ============================================================
     | EDIT / UPDATE
     * ============================================================ */
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

    public function storeTemplate(OperationTemplateStoreRequest $request)
    {
        $user = $request->user();
        $scope = (string) $request->validated('scope');
        $squadronId = $request->validated('squadron_id');

        $this->authorize('create', [OperationTemplate::class, $scope, $squadronId]);

        $template = OperationTemplate::create([
            'name' => $request->validated('name'),
            'scope' => $scope,
            'owner_user_id' => $scope === OperationTemplate::SCOPE_PERSONAL ? $user->id : null,
            'squadron_id' => $scope === OperationTemplate::SCOPE_SQUADRON ? (int) $squadronId : null,
            'created_by' => $user->id,
            'payload' => $this->sanitizeOperationTemplatePayload($request->validated('payload')),
        ]);

        return back()
            ->with('operationTemplate', [
                'event' => 'created',
                'id' => $template->id,
            ]);
    }

    public function updateTemplate(OperationTemplateUpdateRequest $request, OperationTemplate $template)
    {
        $this->authorize('update', $template);

        $data = $request->validated();

        if (array_key_exists('name', $data)) {
            $template->name = $data['name'];
        }

        if (array_key_exists('payload', $data)) {
            $template->payload = $this->sanitizeOperationTemplatePayload($data['payload']);
        }

        $template->save();

        return back()
            ->with('operationTemplate', [
                'event' => 'updated',
                'id' => $template->id,
            ]);
    }

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
            'activeOperation' => $this->resolveActiveOperation($request),
        ]);
    }

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

    protected function operationTemplatesFor(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $this->authorize('viewAny', OperationTemplate::class);

        $user->loadMissing('squadrons');

        $isDirectorLike = $user->hasRole('director') || $user->hasRole('tech_director');
        $activeSquadronIds = $user->squadrons
            ->filter(fn ($squadron) => ($squadron->pivot?->membership_status ?? null) === 'active')
            ->pluck('id')
            ->values()
            ->all();

        return OperationTemplate::query()
            ->where(function ($query) use ($user, $activeSquadronIds, $isDirectorLike) {
                $query->where(function ($nested) use ($user) {
                    $nested->where('scope', OperationTemplate::SCOPE_PERSONAL)
                        ->where('owner_user_id', $user->id);
                });

                $query->orWhere(function ($nested) use ($activeSquadronIds, $isDirectorLike) {
                    $nested->where('scope', OperationTemplate::SCOPE_SQUADRON);

                    if (! $isDirectorLike) {
                        $nested->whereIn('squadron_id', $activeSquadronIds);
                    }
                });

                $query->orWhere(function ($nested) {
                    $nested->where('scope', OperationTemplate::SCOPE_GLOBAL);
                });
            })
            ->orderBy('scope')
            ->orderBy('name')
            ->get()
            ->map(fn (OperationTemplate $template) => $this->presentOperationTemplate($template))
            ->values()
            ->all();
    }

    protected function presentOperationTemplate(OperationTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'scope' => $template->scope,
            'squadron_id' => $template->squadron_id,
            'payload' => $template->payload,
        ];
    }

    protected function sanitizeOperationTemplatePayload(array $payload): array
    {
        if (! array_key_exists('operation_type', $payload) && array_key_exists('operation_kind', $payload)) {
            $payload['operation_type'] = $payload['operation_kind'];
        }

        if (! array_key_exists('gameplay_type', $payload) && array_key_exists('type', $payload)) {
            $payload['gameplay_type'] = $payload['type'];
        }

        if (! array_key_exists('extended_description', $payload) && array_key_exists('notes', $payload)) {
            $payload['extended_description'] = $payload['notes'];
        }

        $allowedKeys = [
            'title',
            'gameplay_type',
            'description',
            'extended_description',
            'visibility',
            'squadron_name',
            'operation_type',
            'branch',
            'operation_strictness',
            'start_location',
            'operation_location',
            'slots',
        ];

        $safe = array_intersect_key($payload, array_flip($allowedKeys));

        if (array_key_exists('slots', $safe)) {
            $safe['slots'] = is_array($safe['slots']) ? array_values($safe['slots']) : [];
        }

        return $safe;
    }
}
