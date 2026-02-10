<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\OperationTemplateStoreRequest;
use App\Http\Requests\Operations\OperationTemplateUpdateRequest;
use App\Models\OperationTemplate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OperationTemplateController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = $request->user();

        $this->authorize('viewAny', OperationTemplate::class);

        $isDirectorLike = $user->hasRole('director') || $user->hasRole('tech_director');

        $activeSquadronIds = $user->squadrons
            ->filter(fn ($s) => ($s->pivot?->membership_status ?? null) === 'active')
            ->pluck('id')
            ->values()
            ->all();

        $templates = OperationTemplate::query()
            ->where(function ($q) use ($user, $activeSquadronIds, $isDirectorLike) {
                $q->where(function ($qq) use ($user) {
                    $qq->where('scope', OperationTemplate::SCOPE_PERSONAL)
                        ->where('owner_user_id', $user->id);
                });

                $q->orWhere(function ($qq) use ($activeSquadronIds, $isDirectorLike) {
                    $qq->where('scope', OperationTemplate::SCOPE_SQUADRON);

                    if (! $isDirectorLike) {
                        $qq->whereIn('squadron_id', $activeSquadronIds);
                    }
                });

                $q->orWhere(function ($qq) {
                    $qq->where('scope', OperationTemplate::SCOPE_GLOBAL);
                });
            })
            ->orderBy('scope')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'ok',
            'payload' => [
                'templates' => $templates->map(fn (OperationTemplate $t) => $this->present($t))->values(),
            ],
        ]);
    }

    public function store(OperationTemplateStoreRequest $request)
    {
        $user = $request->user();

        $scope = (string) $request->validated('scope');
        $squadronId = $request->validated('squadron_id');

        $this->authorize('create', [OperationTemplate::class, $scope, $squadronId]);

        $template = OperationTemplate::create([
            'name' => $request->validated('name'),
            'scope' => $scope,
            'owner_user_id' => $scope === OperationTemplate::SCOPE_PERSONAL ? $user->id : null,
            'squadron_id' => $scope === OperationTemplate::SCOPE_SQUADRON ? (int) $squadronId : null,
            'created_by' => $user->id,
            'payload' => $this->sanitizePayload($request->validated('payload')),
        ]);

        return response()->json([
            'status' => 'ok',
            'payload' => [
                'template' => $this->present($template),
            ],
        ], 201);
    }

    public function update(OperationTemplateUpdateRequest $request, OperationTemplate $template)
    {
        $this->authorize('update', $template);

        $data = $request->validated();

        if (array_key_exists('name', $data)) {
            $template->name = $data['name'];
        }

        if (array_key_exists('payload', $data)) {
            $template->payload = $this->sanitizePayload($data['payload']);
        }

        $template->save();

        return response()->json([
            'status' => 'ok',
            'payload' => [
                'template' => $this->present($template->fresh()),
            ],
        ]);
    }

    public function destroy(Request $request, OperationTemplate $template)
    {
        $this->authorize('delete', $template);

        $template->delete();

        return response()->json([
            'status' => 'ok',
            'payload' => [
                'deleted' => true,
            ],
        ]);
    }

    protected function present(OperationTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'scope' => $template->scope,
            'squadron_id' => $template->squadron_id,
            'payload' => $template->payload,
        ];
    }

    protected function sanitizePayload(array $payload): array
    {
        $allowedKeys = [
            'title',
            'type',
            'description',
            'notes',
            'visibility',
            'squadron_name',
            'operation_kind',
            'branch',
            'operation_strictness',
            'start_location',
            'operation_location',
            'slots',
        ];

        $safe = array_intersect_key($payload, array_flip($allowedKeys));

        if (array_key_exists('slots', $safe)) {
            $safe['slots'] = is_array($safe['slots']) ? array_values($safe['slots']) : [];
        }

        return $safe;
    }
}
