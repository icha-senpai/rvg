<?php

namespace App\Http\Controllers\Web;

use App\Application\Operations\Presenters\OperationPresenter;
use App\Application\Operations\Presenters\OperationPresenterRelations;
use App\Domain\Operations\Enums\CompletionOutcome;
use App\Domain\Operations\Enums\OperationStatus;
use App\Domain\Operations\Services\OperationService;
use App\Domain\Operations\Services\OperationRuntimeService;
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
        protected OperationService $service,
        protected OperationRuntimeService $runtime
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
                    'operation' => OperationPresenter::make(OperationPresenterRelations::loadForFull($updated))->full(),
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
        $channelSyncWarning = null;

        if ($updated->discordChannels()->exists()) {
            try {
                $this->runtime->syncDiscordChannels($updated);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $channelSyncWarning = collect($e->errors())
                    ->flatten()
                    ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                    ->first() ?? 'Discord channel sync failed after the operation started.';
            }
        }

        if ($request->expectsJson()) {
            $response = [
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make(OperationPresenterRelations::loadForFull($updated))->full(),
                ],
            ];

            if ($channelSyncWarning) {
                $response['warning'] = $channelSyncWarning;
            }

            return response()->json($response);
        }

        $response = back()->with('success', 'Operation started.');

        if ($channelSyncWarning) {
            $response->with('warning', 'Operation started, but Discord channel sync needs attention: ' . $channelSyncWarning);
        }

        return $response;
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
        $channelCleanupWarning = null;

        if ($updated->discordChannels()->exists()) {
            $cleanup = $this->runtime->cleanupDiscordChannels($updated);

            if (! ($cleanup['ok'] ?? false)) {
                $channelCleanupWarning = $cleanup['message'] ?? 'Discord channel cleanup failed after the operation was completed.';
            }
        }

        if ($request->expectsJson()) {
            $response = [
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make(OperationPresenterRelations::loadForFull($updated))->full(),
                ],
            ];

            if ($channelCleanupWarning) {
                $response['warning'] = $channelCleanupWarning;
            }

            return response()->json($response);
        }

        $response = back()
            ->with('success', 'Operation completed.');

        if ($channelCleanupWarning) {
            $response->with('warning', 'Operation completed, but Discord channel cleanup needs attention: ' . $channelCleanupWarning);
        }

        return $response;
    }

    /**
     * Cancel an operation with a required cancellation reason.
     */
    public function cancel(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $updated = $this->service->transition($operation, OperationStatus::Canceled->value, $data['reason']);
        $channelCleanupWarning = null;

        if ($updated->discordChannels()->exists()) {
            $cleanup = $this->runtime->cleanupDiscordChannels($updated);

            if (! ($cleanup['ok'] ?? false)) {
                $channelCleanupWarning = $cleanup['message'] ?? 'Discord channel cleanup failed after the operation was canceled.';
            }
        }

        if ($request->expectsJson()) {
            $response = [
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make(OperationPresenterRelations::loadForFull($updated))->full(),
                ],
            ];

            if ($channelCleanupWarning) {
                $response['warning'] = $channelCleanupWarning;
            }

            return response()->json($response);
        }

        $response = back()
            ->with('success', 'Operation canceled.');

        if ($channelCleanupWarning) {
            $response->with('warning', 'Operation canceled, but Discord channel cleanup needs attention: ' . $channelCleanupWarning);
        }

        return $response;
    }
}
