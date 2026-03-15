<?php

namespace App\Http\Middleware;

use App\Domain\AccessControl\AccessService;
use App\Domain\AccessControl\PermissionRegistry;
use App\Domain\Media\MediaService;
use App\Domain\Media\MediaVisibility;
use App\Domain\Media\Presenters\MediaPresenter;
use App\Models\Media;
use App\Models\User;
use Inertia\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        protected AccessService $access,
        protected MediaService $mediaService,
        protected MediaVisibility $mediaVisibility,
    ) {}

    public function share(Request $request): array
    {
        // Pages that MUST NOT receive auth data
        $excluded = [
            'auth/discord*',
        ];

        if ($request->is($excluded)) {
            return [
                'auth' => [
                    'user' => null,
                    'can' => [],
                ]
            ];
        }

        /** @var User|null $user */
        $user = Auth::user();

        $can = [];

        if ($user) {
            $user->load([
                // 🔥 Add RBAC roles to payload
                'roles:id,slug,name',

                // Existing squadron relationship with pivot fields
                'squadrons' => function ($query) {
                    $query->with('emblem');
                    $query->withPivot([
                        'membership_status',
                        'role',
                        'joined_at',
                        'left_at',
                        'removed_at',
                    ]);
                },
            ]);

            $user->squadrons?->each(function ($sq) {
                $emblemUrl = null;
                $emblemEmbedded = null;

                if ($sq->relationLoaded('emblem') && $sq->emblem) {
                    $emblemUrl = $sq->emblem->display_url;
                    $emblemEmbedded = MediaPresenter::make($sq->emblem)->embedded();
                } elseif ($sq->emblem_path) {
                    $emblemUrl = asset('storage/' . $sq->emblem_path);
                }

                $sq->setAttribute('emblem_url', $emblemUrl);

                if ($sq->relationLoaded('emblem')) {
                    $sq->unsetRelation('emblem');
                }

                $sq->setAttribute('emblem', $emblemEmbedded);
            });

            if ($this->access->isDirectorLike($user)) {
                $can = array_fill_keys(PermissionRegistry::all(), true);
            } else {
                $permissionSlugs = $this->access->context($user)->permissions()->pluck('slug')->all();
                $permissionSlugSet = array_fill_keys($permissionSlugs, true);

                $can = [];
                foreach (PermissionRegistry::all() as $slug) {
                    $can[$slug] = isset($permissionSlugSet[$slug]);
                }
            }
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user
                    ? $user
                    : null,
                'can' => $can,
            ],
            'flash' => [
                'operation' => fn () => $request->session()->get('operation'),
                'operationTemplate' => fn () => $request->session()->get('operationTemplate'),
                'media' => fn () => $request->session()->get('media'),
                'success' => fn () => $request->session()->get('success'),
            ],
            'mediaPicker' => fn () => $this->resolveMediaPicker($request),
        ]);
    }

    protected function resolveMediaPicker(Request $request): ?array
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
