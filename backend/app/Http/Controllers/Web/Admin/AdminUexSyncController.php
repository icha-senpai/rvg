<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RunUexSyncRequest;
use App\Services\UexSyncService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;

class AdminUexSyncController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected UexSyncService $syncService,
    ) {}

    public function store(RunUexSyncRequest $request)
    {
        $this->authorize('access-admin-panel');

        $scope = (string) ($request->validated('scope') ?? 'all');
        $resources = collect($request->validated('resources', []))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        @set_time_limit(0);

        try {
            $run = $this->syncService->sync($scope, $resources);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', 'UEX sync failed before completion: ' . $e->getMessage());
        }

        $summary = "UEX sync finished with status [{$run->status}]. "
            . "{$run->successful_resources} resource(s) succeeded, "
            . "{$run->failed_resources} failed, "
            . "{$run->total_records} row(s) processed.";

        return redirect()
            ->route('admin.dashboard')
            ->with($run->status === 'success' ? 'success' : 'error', $summary);
    }
}
