<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Domain\Media\MediaVisibility;
use App\Domain\Media\MediaService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Handles the admin media library screens and related JSON helper endpoints.
 *
 * This controller keeps authorization, filtering, redirects, and response shape
 * concerns in one place while the media domain services own persistence and
 * visibility rules.
 */
class MediaController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected MediaService $service,
        protected MediaVisibility $visibility
    ) {}

    /**
     * Render the admin media library panel with the current filters and stats.
     */
    public function index(Request $request)
    {
        $this->authorize('access-admin-panel');

        $filters = [
            'collection' => $request->query('collection'),
            'search'     => trim((string) $request->query('search', '')),
            'mime_type'  => $request->query('mime_type'),
        ];

        $page = max(1, (int) $request->query('page', 1));

        $media = $this->service->listPresented($filters, 24, 'full', 'page', $page);
        $stats = $this->service->mediaStats(true);

        return Inertia::render('Admin/Partials/MediaPanel', [
            'media'   => [
                'data' => $media->items(),
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'prev_page_url' => $media->previousPageUrl(),
                'next_page_url' => $media->nextPageUrl(),
                'total' => $media->total(),
            ],
            'filters' => $filters,
            'stats'   => $stats,
            'collections' => Media::COLLECTIONS,
        ]);
    }

    /**
     * Return the media list as JSON for panel refreshes and picker modals.
     */
    public function list(Request $request)
    {
        $this->authorize('viewAny', Media::class);

        $filters = [
            'collection' => $request->query('collection'),
            'search'     => trim((string) $request->query('search', '')),
            'mime_type'  => $request->query('mime_type'),
        ];
        $squadronId = $request->query('squadron_id');
        $squadronId = $squadronId !== null ? (int) $squadronId : null;

        $result = $this->visibility->applyListVisibility($request->user(), $filters, $squadronId);
        if ($result->validationErrorMessage !== null) {
            return response()->json([
                'status' => 'error',
                'message' => $result->validationErrorMessage,
            ], 422);
        }

        $filters = $result->filters;

        $perPage = (int) $request->query('per_page', 24);
        $perPage = max(1, min(100, $perPage));

        $media = $this->service->listPresented($filters, $perPage);
        $stats = $this->service->mediaStats();

        return response()->json([
            'status'  => 'ok',
            'payload' => [
                'media' => $media,
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Upload a new media file and return either JSON or a flashed redirect
     * depending on the caller.
     */
    public function upload(Request $request)
    {
        $data = $request->validate([
            'file'       => ['required', 'file', 'max:51200'],
            'collection' => ['required', 'string', 'in:' . implode(',', Media::COLLECTIONS)],
            'alt_text'   => ['nullable', 'string', 'max:255'],
            'squadron_id' => ['nullable', 'integer', 'exists:squadrons,id'],
        ]);

        $collection = $data['collection'];
        $user = $request->user();
        $squadronId = $data['squadron_id'] ?? null;

        // Upload permissions depend on both the destination collection and the
        // optional squadron scope attached to the upload.
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
                'payload' => ['media' => $this->service->present($media, 'full')],
            ], 201);
        }

        return $this->redirectWithMediaFlash($request, $media, 'uploaded', 'File uploaded.');
    }

    /**
     * Update editable media metadata such as alt text or the display filename.
     */
    public function update(Request $request, Media $media)
    {
        $this->authorize('update', $media);

        $data = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
            'original_filename' => ['sometimes', 'string', 'max:255', 'regex:/\S/', 'not_regex:/[\\/\\\\]/'],
        ]);

        $media = $this->service->updateMetadata($media, $data);

        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'ok',
                'payload' => ['media' => $this->service->present($media, 'full')],
            ]);
        }

        return $this->redirectWithMediaFlash($request, $media, 'updated', 'Media updated.');
    }

    /**
     * Delete a media record and adapt the response to JSON or a flashed redirect.
     */
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

        return back()
            ->with('success', 'Media deleted.')
            ->with('media', [
                'event' => 'deleted',
                'id' => $media->id,
            ]);
    }

    /**
     * Return one fully presented media record for drawers, modals, or detail
     * panels.
     */
    public function show(Request $request, Media $media)
    {
        $this->authorize('view', $media);

        return response()->json([
            'status'  => 'ok',
            'payload' => ['media' => $this->service->present($media, 'full')],
        ]);
    }

    /**
     * Stream a media file download for admin users.
     */
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

    /**
     * Redirect back with the standard flashed media event payload used by the
     * admin media UI.
     */
    protected function redirectWithMediaFlash(Request $request, Media $media, string $event, string $success): RedirectResponse
    {
        return back()
            ->with('success', $success)
            ->with('media', [
                'event' => $event,
                'item' => $this->service->present($media, 'full'),
            ]);
    }
}
