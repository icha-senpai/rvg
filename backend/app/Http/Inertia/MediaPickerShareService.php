<?php

namespace App\Http\Inertia;

use App\Domain\Media\MediaService;
use App\Domain\Media\MediaVisibility;
use App\Domain\Media\Presenters\MediaPresenter;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MediaPickerShareService
{
    public function __construct(
        protected MediaService $mediaService,
        protected MediaVisibility $mediaVisibility,
    ) {}

    public function forRequest(Request $request): ?array
    {
        if (! $request->boolean('media_picker')) {
            return null;
        }

        $collection = trim((string) $request->query('media_picker_collection', ''));
        if ($collection === '') {
            return null;
        }

        $user = $request->user();
        if (! $user || Gate::forUser($user)->denies('viewAny', Media::class)) {
            return null;
        }

        $search = trim((string) $request->query('media_picker_search', ''));
        $page = max(1, (int) $request->query('media_picker_page', 1));

        $squadronIdRaw = $request->query('media_picker_squadron_id');
        $squadronId = is_numeric($squadronIdRaw)
            ? (int) $squadronIdRaw
            : null;

        $filters = [
            'collection' => $collection,
            'search' => $search,
        ];

        $result = $this->mediaVisibility->applyListVisibility($user, $filters, $squadronId);
        if ($result->validationErrorMessage !== null) {
            return [
                'collection' => $collection,
                'search' => $search,
                'squadronId' => $squadronId,
                'items' => [],
                'pagination' => [
                    'currentPage' => 1,
                    'lastPage' => 1,
                    'prevUrl' => null,
                    'nextUrl' => null,
                    'total' => 0,
                ],
                'error' => $result->validationErrorMessage,
            ];
        }

        $media = $this->mediaService->list($result->filters, 24, 'media_picker_page', $page);
        $media->setCollection(
            $media->getCollection()->map(
                fn (Media $item) => MediaPresenter::make($item)->summary()
            )
        );

        return [
            'collection' => $collection,
            'search' => $search,
            'squadronId' => $squadronId,
            'items' => $media->items(),
            'pagination' => [
                'currentPage' => $media->currentPage(),
                'lastPage' => $media->lastPage(),
                'prevUrl' => $media->previousPageUrl(),
                'nextUrl' => $media->nextPageUrl(),
                'total' => $media->total(),
            ],
            'error' => null,
        ];
    }
}
