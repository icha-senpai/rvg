<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Domain\Media\MediaVisibility;
use App\Domain\Media\MediaService;
use App\Domain\Media\Presenters\MediaPresenter;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MediaApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected MediaService $service,
        protected MediaVisibility $visibility
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
