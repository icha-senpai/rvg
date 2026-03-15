<?php

namespace App\Http\Controllers\Api\v1;

use App\Domain\Operations\Services\OperationTemplateService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\OperationTemplateStoreRequest;
use App\Http\Requests\Operations\OperationTemplateUpdateRequest;
use App\Models\OperationTemplate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * JSON API controller for operation template listing and mutation actions.
 */
class OperationTemplateController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OperationTemplateService $templates
    ) {}

    /**
     * Return the set of templates visible to the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $this->authorize('viewAny', OperationTemplate::class);

        return response()->json([
            'status' => 'ok',
            'message' => null,
            'payload' => [
                'templates' => $this->templates->listVisibleFor($user),
            ],
        ]);
    }

    /**
     * Create a new operation template within the requested visibility scope.
     */
    public function store(OperationTemplateStoreRequest $request)
    {
        $user = $request->user();

        $scope = (string) $request->validated('scope');
        $squadronId = $request->validated('squadron_id');

        $this->authorize('create', [OperationTemplate::class, $scope, $squadronId]);

        $template = $this->templates->create(
            $user,
            $scope,
            $scope === OperationTemplate::SCOPE_SQUADRON ? (int) $squadronId : null,
            $request->validated('name'),
            $request->validated('payload')
        );

        return response()->json([
            'status' => 'ok',
            'message' => null,
            'payload' => [
                'template' => $this->templates->present($template),
            ],
        ], 201);
    }

    /**
     * Update an existing operation template.
     */
    public function update(OperationTemplateUpdateRequest $request, OperationTemplate $template)
    {
        $this->authorize('update', $template);

        $updated = $this->templates->update($template, $request->validated());

        return response()->json([
            'status' => 'ok',
            'message' => null,
            'payload' => [
                'template' => $this->templates->present($updated),
            ],
        ]);
    }

    /**
     * Delete an operation template.
     */
    public function destroy(Request $request, OperationTemplate $template)
    {
        $this->authorize('delete', $template);

        $template->delete();

        return response()->json([
            'status' => 'ok',
            'message' => null,
            'payload' => [
                'deleted' => true,
            ],
        ]);
    }
}
