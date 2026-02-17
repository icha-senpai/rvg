<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\AccessControl\RoleHierarchy;
use App\Models\Media;
use App\Models\Squadron;
use App\Domain\Media\MediaService;
use App\Domain\Media\Presenters\MediaPresenter;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MediaController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected MediaService $service
    ) {}

    /* ============================================================
     | ADMIN MEDIA LIBRARY (paginated list for admin dashboard)
     * ============================================================ */

    public function index(Request $request)
    {
        $this->authorize('access-admin-panel');

        $filters = [
            'collection' => $request->query('collection'),
            'search'     => trim((string) $request->query('search', '')),
            'mime_type'  => $request->query('mime_type'),
        ];

        $media = $this->service->list($filters, 24);

        $media->setCollection(
            $media->getCollection()->map(
                fn (Media $m) => MediaPresenter::make($m)->full()
            )
        );

        // Gather summary stats for the dashboard header
        $stats = [
            'total'       => Media::count(),
            'total_size'  => Media::sum('size'),
            'by_collection' => Media::selectRaw('collection, COUNT(*) as count')
                ->groupBy('collection')
                ->pluck('count', 'collection'),
        ];

        return Inertia::render('Admin/Partials/MediaPanel', [
            'media'   => $media,
            'filters' => $filters,
            'stats'   => $stats,
            'collections' => Media::COLLECTIONS,
        ]);
    }

    /**
     * Return media list as JSON (for AJAX panel refresh / picker modal).
     */
    public function list(Request $request)
    {
        $this->authorize('viewAny', Media::class);

        $filters = [
            'collection' => $request->query('collection'),
            'search'     => trim((string) $request->query('search', '')),
            'mime_type'  => $request->query('mime_type'),
        ];

        $user = $request->user();
        $collection = (string) ($filters['collection'] ?? '');
        $squadronId = $request->query('squadron_id');
        $squadronId = $squadronId !== null ? (int) $squadronId : null;

        $isDirectorLike = $user
            ? ($user->hasRole('director') || $user->hasRole('tech_director'))
            : false;

        if ($user) {
            $user->loadMissing('roles:id,slug');
        }

        $isOfficer = $user
            ? (RoleHierarchy::userAtLeast($user, 'lieutenant') || (int) ($user->rank_level ?? 0) >= 2)
            : false;

        $canUseAdminCollections = $isOfficer;

        if ($collection === Media::COLLECTION_SHIP_IMAGE
            || $collection === Media::COLLECTION_SITE_ASSET) {
            if (! $isDirectorLike && ! $canUseAdminCollections) {
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
            $canBrowseEmblems = $squadron
                && $user
                && (
                    $user->isSquadronLeader($squadron)
                    || (
                        $isOfficer
                        && $user->squadronMemberships()->active()->where('squadron_id', $squadron->id)->exists()
                    )
                );

            if (! $canBrowseEmblems) {
                abort(403);
            }
        }

        $canBrowseOperationImages = false;
        if ($user) {
            $canBrowseOperationImages = $isOfficer;
        }

        $publicCollections = [];

        if (! $isDirectorLike) {
            if (! $collection) {
                $filters['uploaded_by'] = $user?->id;
            } elseif ($collection === Media::COLLECTION_OPERATION_IMAGE) {
                if (! $canBrowseOperationImages) {
                    $filters['uploaded_by'] = $user?->id;
                }
            } elseif ($collection === Media::COLLECTION_SHIP_IMAGE
                || $collection === Media::COLLECTION_SITE_ASSET) {
            } elseif ($collection === Media::COLLECTION_SQUADRON_EMBLEM) {
                // squadron leader may browse the emblem library
            } elseif (! in_array($collection, $publicCollections, true)) {
                $filters['uploaded_by'] = $user?->id;
            }
        }

        $perPage = (int) $request->query('per_page', 24);
        $perPage = max(1, min(100, $perPage));

        $media = $this->service->list($filters, $perPage);

        $media->setCollection(
            $media->getCollection()->map(
                fn (Media $m) => MediaPresenter::make($m)->summary()
            )
        );

        $stats = [
            'total' => Media::count(),
            'total_size' => Media::sum('size'),
        ];

        return response()->json([
            'status'  => 'ok',
            'payload' => [
                'media' => $media,
                'stats' => $stats,
            ],
        ]);
    }

    /* ============================================================
     | UPLOAD
     * ============================================================ */

    public function upload(Request $request)
    {
        $data = $request->validate([
            'file'       => ['required', 'file', 'max:51200'], // 50 MB in KB
            'collection' => ['required', 'string', 'in:' . implode(',', Media::COLLECTIONS)],
            'alt_text'   => ['nullable', 'string', 'max:255'],
            'squadron_id' => ['nullable', 'integer', 'exists:squadrons,id'],
        ]);

        $collection = $data['collection'];
        $user = $request->user();
        $squadronId = $data['squadron_id'] ?? null;

        // Policy check: can this user upload to this collection?
        $this->authorize('upload', [Media::class, $collection, $squadronId]);

        $media = $this->service->upload(
            $request->file('file'),
            $user,
            $collection,
            [
                'alt_text' => $data['alt_text'] ?? null,
            ]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'ok',
                'payload' => ['media' => MediaPresenter::make($media)->full()],
            ], 201);
        }

        return back()->with('success', 'File uploaded.');
    }

    /* ============================================================
     | UPDATE METADATA (alt_text, collection)
     * ============================================================ */

    public function update(Request $request, Media $media)
    {
        $this->authorize('update', $media);

        $data = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $media->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'ok',
                'payload' => ['media' => MediaPresenter::make($media->fresh())->full()],
            ]);
        }

        return back()->with('success', 'Media updated.');
    }

    /* ============================================================
     | DELETE
     * ============================================================ */

    public function destroy(Request $request, Media $media)
    {
        $this->authorize('delete', $media);

        $this->service->delete($media);

        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'ok',
                'message' => 'Media deleted.',
            ]);
        }

        return back()->with('success', 'Media deleted.');
    }

    /* ============================================================
     | SHOW SINGLE (JSON detail for modals / drawers)
     * ============================================================ */

    public function show(Request $request, Media $media)
    {
        $this->authorize('view', $media);

        return response()->json([
            'status'  => 'ok',
            'payload' => ['media' => MediaPresenter::make($media)->full()],
        ]);
    }

    public function download(Request $request, Media $media)
    {
        $this->authorize('access-admin-panel');

        $disk = $media->disk;
        $path = $media->path;

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        $stream = Storage::disk($disk)->readStream($path);

        if ($stream === false) {
            abort(404);
        }

        $filename = $media->original_filename ?: basename($path);
        $mime = $media->mime_type ?: 'application/octet-stream';

        return response()->streamDownload(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $filename, [
            'Content-Type' => $mime,
        ]);
    }
}
