<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Domain\Media\MediaService;
use App\Domain\Media\Presenters\MediaPresenter;
use App\Domain\AccessControl\RoleHierarchy;
use App\Models\Squadron;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MediaApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected MediaService $service
    ) {}

    /**
     * List media (filtered by collection, search, etc.)
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Media::class);

        $filters = [
            'collection' => $request->query('collection'),
            'search'     => trim((string) $request->query('search', '')),
        ];

        $user = $request->user();
        $collection = (string) ($filters['collection'] ?? '');
        $squadronId = $request->query('squadron_id');
        $squadronId = $squadronId !== null ? (int) $squadronId : null;

        $isDirectorLike = $user
            ? ($user->hasRole('director') || $user->hasRole('tech_director'))
            : false;

        if ($collection === Media::COLLECTION_SHIP_IMAGE
            || $collection === Media::COLLECTION_SITE_ASSET) {
            if (! $isDirectorLike) {
                abort(403);
            }
        }

        if ($collection === Media::COLLECTION_SQUADRON_EMBLEM && ! $isDirectorLike) {
            if (! $squadronId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'squadron_id is required for squadron emblems.',
                ], 422);
            }

            $squadron = Squadron::find($squadronId);
            if (! $squadron || ! $user || ! $user->isSquadronLeader($squadron)) {
                abort(403);
            }
        }

        $canBrowseOperationImages = false;
        if ($user) {
            $canBrowseOperationImages = (int) ($user->rank_level ?? 0) >= 2;

            if (! $canBrowseOperationImages) {
                $user->loadMissing('roles:id,slug');
                $canBrowseOperationImages = RoleHierarchy::userAtLeast($user, 'lieutenant');
            }
        }

        $publicCollections = [
            // intentionally none; restricted collections are handled above
        ];

        if (! $isDirectorLike) {
            if (! $collection) {
                $filters['uploaded_by'] = $user?->id;
            } elseif ($collection === Media::COLLECTION_OPERATION_IMAGE) {
                if (! $canBrowseOperationImages) {
                    $filters['uploaded_by'] = $user?->id;
                }
            } elseif ($collection === Media::COLLECTION_SQUADRON_EMBLEM) {
                // squadron leader may browse the emblem library
            } elseif (! in_array($collection, $publicCollections, true)) {
                $filters['uploaded_by'] = $user?->id;
            }
        }

        $media = $this->service->list($filters, 24);

        $media->setCollection(
            $media->getCollection()->map(
                fn (Media $m) => MediaPresenter::make($m)->summary()
            )
        );

        return response()->json([
            'status'  => 'ok',
            'message' => null,
            'payload' => ['media' => $media],
        ]);
    }

    /**
     * Show a single media record.
     */
    public function show(Media $media)
    {
        $this->authorize('view', $media);

        return response()->json([
            'status'  => 'ok',
            'message' => null,
            'payload' => ['media' => MediaPresenter::make($media)->full()],
        ]);
    }

    /**
     * Upload a file.
     */
    public function upload(Request $request)
    {
        $data = $request->validate([
            'file'       => ['required', 'file', 'max:51200'],
            'collection' => ['required', 'string', 'in:' . implode(',', Media::COLLECTIONS)],
            'alt_text'   => ['nullable', 'string', 'max:255'],
            'squadron_id' => ['nullable', 'integer', 'exists:squadrons,id'],
        ]);

        $this->authorize('upload', [Media::class, $data['collection'], $data['squadron_id'] ?? null]);

        $media = $this->service->upload(
            $request->file('file'),
            $request->user(),
            $data['collection'],
            ['alt_text' => $data['alt_text'] ?? null]
        );

        return response()->json([
            'status'  => 'ok',
            'message' => null,
            'payload' => ['media' => MediaPresenter::make($media)->full()],
        ], 201);
    }

    /**
     * Delete a media record.
     */
    public function destroy(Media $media)
    {
        $this->authorize('delete', $media);

        $this->service->delete($media);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Media deleted.',
        ]);
    }
}
