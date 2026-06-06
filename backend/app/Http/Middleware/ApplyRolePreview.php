<?php

namespace App\Http\Middleware;

use App\Services\RolePreviewService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApplyRolePreview
{
    public function __construct(
        protected RolePreviewService $rolePreview,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $originalUser = $request->user();

        if (! $originalUser) {
            return $next($request);
        }

        $request->attributes->set(RolePreviewService::REQUEST_ORIGINAL_USER_KEY, $originalUser);

        $activeRoleSlug = $this->rolePreview->activeRoleSlug($request, $originalUser);
        $effectiveUser = $this->rolePreview->projectedUser($originalUser, $activeRoleSlug);

        if ($effectiveUser && $effectiveUser !== $originalUser) {
            Auth::setUser($effectiveUser);
            $request->setUserResolver(fn () => $effectiveUser);
        }

        return $next($request);
    }
}
