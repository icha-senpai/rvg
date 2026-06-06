<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\RolePreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RolePreviewController extends Controller
{
    public function __construct(
        protected RolePreviewService $rolePreview,
    ) {}

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role_slug' => ['nullable', 'string'],
        ]);

        $managerUser = $this->rolePreview->managerUser($request, $request->user());

        $this->rolePreview->setActiveRoleSlug(
            $request,
            $managerUser,
            $data['role_slug'] ?? null,
        );

        return back(303);
    }
}
