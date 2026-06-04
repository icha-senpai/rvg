<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\UexAdminDataService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class AdminUexDataController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected UexAdminDataService $data,
    ) {}

    public function index(Request $request, string $resource)
    {
        $this->authorize('access-admin-panel');

        $search = trim((string) $request->query('search', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(10, (int) $request->query('per_page', 20)));

        return response()->json([
            'status' => 'success',
            'payload' => $this->data->resourcePayload($resource, $search, $page, $perPage),
        ]);
    }
}
