<?php

namespace App\Http\Controllers\Web;

use App\Application\Operations\Presenters\OperationPresenter;
use App\Domain\Operations\Enums\CompletionOutcome;
use App\Domain\Operations\Enums\OperationStatus;
use App\Domain\Operations\Services\OperationService;
use App\Http\Controllers\Controller;
use App\Models\Operation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles the focused web actions that move an operation between lifecycle
 * states.
 */
class OperationTransitionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OperationService $service
    ) {}

    /**
     * Publish a draft operation.
     *
     * The action returns JSON for modal/editor flows and redirects for normal web
     * form submissions.
     */
    public function publish(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        Log::info("🟢 publish() endpoint hit for operation {$operation->id}");

        $updated = $this->service->transition($operation, OperationStatus::Published->value);

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

    /**
     * Start a published operation.
     */
    public function start(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $updated = $this->service->transition($operation, OperationStatus::InProgress->value);

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

    /**
     * Complete an operation with an explicit success or failure outcome.
     */
    public function complete(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $data = $request->validate([
            'outcome' => 'required|in:' . implode(',', CompletionOutcome::values()),
        ]);

        $updated = $this->service->transition($operation, OperationStatus::Completed->value, null, $data['outcome']);

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

    /**
     * Cancel an operation with an optional cancellation reason.
     */
    public function cancel(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $updated = $this->service->transition($operation, OperationStatus::Canceled->value, $data['reason'] ?? null);

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
