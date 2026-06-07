<?php

namespace App\Http\Middleware;

use App\Domain\AccessControl\AccessService;
use App\Domain\AccessControl\PermissionRegistry;
use App\Http\Inertia\ArchiveNavigationShareService;
use App\Http\Inertia\MediaPickerShareService;
use App\Http\Inertia\PendingInertiaDataService;
use App\Models\User;
use App\Services\LedgerFeatureService;
use App\Services\RolePreviewService;
use Inertia\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        protected AccessService $access,
        protected ArchiveNavigationShareService $archiveNavigation,
        protected MediaPickerShareService $mediaPicker,
        protected PendingInertiaDataService $pendingData,
        protected LedgerFeatureService $ledgerFeature,
        protected RolePreviewService $rolePreview,
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

        /** @var User|null $effectiveUser */
        $effectiveUser = Auth::user();
        $managerUser = $this->rolePreview->managerUser($request, $effectiveUser);
        $can = [];
        $ledgerEnabled = false;

        if ($effectiveUser) {
            $this->loadSharedUserGraph($effectiveUser);

            if ($this->access->isDirectorLike($effectiveUser)) {
                $can = array_fill_keys(PermissionRegistry::all(), true);
            } else {
                $permissionSlugs = $this->access->context($effectiveUser)->permissions()->pluck('slug')->all();
                $permissionSlugSet = array_fill_keys($permissionSlugs, true);

                foreach (PermissionRegistry::all() as $slug) {
                    $can[$slug] = isset($permissionSlugSet[$slug]);
                }
            }

            $ledgerEnabled = $this->ledgerFeature->canAccess($effectiveUser);
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $effectiveUser
                    ? $effectiveUser
                    : null,
                'can' => $can,
            ],
            'features' => [
                'ledger' => $ledgerEnabled,
            ],
            'rolePreview' => $this->rolePreview->share($request, $managerUser),
            'flash' => [
                'operation' => fn () => $request->session()->get('operation'),
                'operationTemplate' => fn () => $request->session()->get('operationTemplate'),
                'media' => fn () => $request->session()->get('media'),
                'success' => fn () => $request->session()->get('success'),
            ],
            'mediaPicker' => fn () => $this->mediaPicker->forRequest($request),
            'archiveNavigation' => fn () => $this->archiveNavigation->forUser($effectiveUser),
            'pendingTransferBadges' => fn () => $this->pendingData->transferBadgesFor($effectiveUser),
            'pendingSquadronApplications' => fn () => $this->pendingData->pendingSquadronApplicationsFor($effectiveUser),
        ]);
    }

    protected function loadSharedUserGraph(User $user): void
    {
        $user->loadMissing([
            'roles:id,slug,name',
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
                $emblemEmbedded = \App\Domain\Media\Presenters\MediaPresenter::make($sq->emblem)->embedded();
            } elseif ($sq->emblem_path) {
                $emblemUrl = asset('storage/' . $sq->emblem_path);
            }

            $sq->setAttribute('emblem_url', $emblemUrl);

            if ($sq->relationLoaded('emblem')) {
                $sq->unsetRelation('emblem');
            }

            $sq->setAttribute('emblem', $emblemEmbedded);
        });
    }
}
