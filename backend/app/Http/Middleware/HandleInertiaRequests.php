<?php

namespace App\Http\Middleware;

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
                ]
            ];
        }

        $user = Auth::user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user
                    ? $user->load([
                        // 🔥 Add RBAC roles to payload
                        'roles:id,slug,name',

                        // Existing squadron relationship with pivot fields
                        'squadrons' => function ($query) {
                            $query->withPivot([
                                'membership_status',
                                'role',
                                'joined_at',
                                'left_at',
                                'removed_at',
                            ]);
                        },
                    ])
                    : null,
            ],
        ]);
    }
}
