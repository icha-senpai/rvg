<?php

namespace App\Http\Controllers\Web;

use App\Domain\Operations\Presenters\OperationPresenter;
use App\Domain\Operations\Services\OperationService;
use App\Http\Controllers\Controller;
use App\Models\Operation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OperationTransitionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OperationService $service
    ) {}

    public function publish(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        Log::info("🟢 publish() endpoint hit for operation {$operation->id}");

        $updated = $this->service->transition($operation, 'published');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make($updated)->full(),
                ],
            ]);
        }

        if ($request->boolean('stay_on_page')) {
            return back()
                ->with('success', 'Operation published successfully.')
                ->with('operation', [
                    'event' => 'published',
                    'id' => $updated->id,
                ]);
        }

        return redirect()
            ->route('operations.show', $operation->id)
            ->with('success', 'Operation published successfully.');
    }

    public function start(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $updated = $this->service->transition($operation, 'in_progress');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make($updated)->full(),
                ],
            ]);
        }

        return back()
            ->with('success', 'Operation started.');
    }

    public function complete(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $data = $request->validate([
            'outcome' => 'required|in:success,failed',
        ]);

        $updated = $this->service->transition($operation, 'completed', null, $data['outcome']);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make($updated)->full(),
                ],
            ]);
        }

        return back()
            ->with('success', 'Operation completed.');
    }

    public function cancel(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $updated = $this->service->transition($operation, 'canceled', $data['reason'] ?? null);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make($updated)->full(),
                ],
            ]);
        }

        return back()
            ->with('success', 'Operation canceled.');
    }
}
