<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Domain\Media\MediaVisibility;
use App\Domain\Media\MediaService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * JSON API controller for media listing, detail, upload, and deletion.
 *
 * Visibility filtering and persistence stay in the media domain services while
 * this controller handles authorization, validation, and response shape.
 */
class MediaApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected MediaService $service,
        protected MediaVisibility $visibility
    ) {}

    /**
     * Return the media list after applying the caller's visibility rules.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Media::class);

        $filters = [
            'collection' => $request->query('collection'),
            'search'     => trim((string) $request->query('search', '')),
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

        $media = $this->service->listPresented($filters, 24);

        return response()->json([
            'status'  => 'ok',
            'message' => null,
            'payload' => ['media' => $media],
        ]);
    }

    /**
     * Return one fully presented media record.
     */
    public function show(Media $media)
    {
        $this->authorize('view', $media);

        return response()->json([
            'status'  => 'ok',
            'message' => null,
            'payload' => ['media' => $this->service->present($media, 'full')],
        ]);
    }

    /**
     * Upload a new media file through the API.
     */
    public function upload(Request $request)
    {
        $data = $request->validate([
            'file'       => ['required', 'file', 'max:51200'],
            'collection' => ['required', 'string', 'in:' . implode(',', Media::COLLECTIONS)],
            'alt_text'   => ['nullable', 'string', 'max:255'],
            'squadron_id' => ['nullable', 'integer', 'exists:squadrons,id'],
        ]);

        // Upload permissions depend on the target collection and the optional
        // squadron scope associated with the upload.
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
            'payload' => ['media' => $this->service->present($media, 'full')],
        ], 201);
    }

    /**
     * Delete a media record through the API.
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
