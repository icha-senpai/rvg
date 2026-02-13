<?php

namespace App\Http\Middleware;

use App\Domain\AccessControl\PermissionRegistry;
use App\Domain\Media\Presenters\MediaPresenter;
use App\Models\User;
use Inertia\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        // Pages that MUST NOT receive auth data
        $excluded = [
            'verify*',
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

            if ($user->hasRole('director') || $user->hasRole('tech_director')) {
                $can = array_fill_keys(PermissionRegistry::all(), true);
            } else {
                $permissionSlugs = $user->permissions()->pluck('slug')->all();
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
        ]);
    }
}
