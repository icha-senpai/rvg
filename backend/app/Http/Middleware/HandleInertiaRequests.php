<?php

namespace App\Http\Middleware;

use Inertia\Middleware;
use Illuminate\Http\Request;

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

        $user = $request->user();

        return [
            'auth' => [
                'user' => $user ? [
                    'id'        => $user->id,
                    'name'      => $user->name,
                    'rank'      => $user->rank,
                    'squadrons' => $user->squadron()->get(),
                ] : null,
            ],
        ];
    }
}
