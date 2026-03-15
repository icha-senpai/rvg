<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Renders the unified admin dashboard shell.
 */
class AdminController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected AdminDashboardService $dashboard,
    ) {}

    /**
     * Render the admin dashboard with its current search filter.
     */
    public function dashboard(Request $request)
    {
        $this->authorize('access-admin-panel');

        $search = trim((string) $request->input('search', ''));

        return Inertia::render('Admin/Dashboard', $this->dashboard->build($search));
    }
}
