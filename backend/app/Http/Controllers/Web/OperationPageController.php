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
use App\Domain\Operations\Services\OperationShowDataService;
use App\Domain\Operations\Presenters\OperationPresenter;
use App\Domain\Operations\Queries\OperationQuery;
use App\Domain\Media\MediaService;
use App\Models\Media;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OperationPageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OperationService    $service,
        protected OperationQuery      $query,
        protected MediaService        $media,
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

        $now = now();

        $status = (string) $request->query('status', 'active');
        $search = trim((string) $request->query('search', ''));

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
            ->paginate(12)
            ->withQueryString();

        $operations->setCollection(
            $operations->getCollection()
                ->map(fn ($op) => OperationPresenter::make($op)->summary())
        );

        return Inertia::render('Operations/OperationDashboard', [
            'operations' => $operations,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    /* ============================================================
     | SHOW SINGLE OPERATION
     * ============================================================ */
    public function show(Request $request, Operation $operation)
    {
        $this->authorize('view', $operation);

        // Load full graph for display
        $operation = $this->service->loadGraph($operation);
        $user = $request->user();

        $participants = $operation->participants;

        // Group participants by slot
        $participantsBySlot = $participants->groupBy(function ($p) {
            return $p->slot ?: 'unassigned';
        });

        $unassigned = $participants->filter(fn ($p) => !$p->slot)->values();

        $currentParticipant = $participants->firstWhere('user_id', $user->id);

        return Inertia::render('Operations/MissionShow', [
            'operation'              => OperationPresenter::make($operation)->full(),
            'authUser'               => $user,
            'participants'           => $participants,
            'participantsBySlot'     => $participantsBySlot,
            'unassignedParticipants' => $unassigned,
            'currentParticipant'     => $currentParticipant,
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

        $mediaId = $request->input('media_id');

        // Detach current images if clearing
        if (empty($mediaId)) {
            Media::where('mediable_type', Operation::class)
                ->where('mediable_id', $operation->id)
                ->where('collection', Media::COLLECTION_OPERATION_IMAGE)
                ->update([
                    'mediable_type' => null,
                    'mediable_id'   => null,
                ]);
            return;
        }

        $media = Media::find($mediaId);

        if ($media && $media->collection === Media::COLLECTION_OPERATION_IMAGE) {
            if ($media->mediable_type !== null
                && $media->mediable_id !== null
                && ($media->mediable_type !== Operation::class || (int) $media->mediable_id !== (int) $operation->id)) {
                $mediaCopy = $media->replicate(['mediable_type', 'mediable_id']);
                $mediaCopy->mediable_type = null;
                $mediaCopy->mediable_id = null;
                $mediaCopy->save();
                $media = $mediaCopy;
            }

            Media::where('mediable_type', Operation::class)
                ->where('mediable_id', $operation->id)
                ->where('collection', Media::COLLECTION_OPERATION_IMAGE)
                ->where('id', '!=', $media->id)
                ->update([
                    'mediable_type' => null,
                    'mediable_id'   => null,
                ]);

            $this->media->attach($media, $operation);
        }
    }
}
