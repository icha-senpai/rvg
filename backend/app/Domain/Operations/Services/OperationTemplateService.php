<?php

namespace App\Domain\Operations\Services;

use App\Domain\AccessControl\OperationTemplateAccessService;
use App\Models\OperationTemplate;
use App\Models\User;

class OperationTemplateService
{
    public function __construct(
        protected OperationTemplateAccessService $access
    ) {}

    public function listVisibleFor(User $user): array
    {
        $isDirectorLike = $this->access->isDirectorLike($user);
        $activeSquadronIds = $this->access->visibleOperationTemplateSquadronIds($user);

        return OperationTemplate::query()
            ->where(function ($query) use ($user, $activeSquadronIds, $isDirectorLike) {
                $query->where(function ($nested) use ($user) {
                    $nested->where('scope', OperationTemplate::SCOPE_PERSONAL)
                        ->where('owner_user_id', $user->id);
                });

                $query->orWhere(function ($nested) use ($activeSquadronIds, $isDirectorLike) {
                    $nested->where('scope', OperationTemplate::SCOPE_SQUADRON);

                    if (! $isDirectorLike) {
                        $nested->whereIn('squadron_id', $activeSquadronIds);
                    }
                });

                $query->orWhere(function ($nested) {
                    $nested->where('scope', OperationTemplate::SCOPE_GLOBAL);
                });
            })
            ->orderBy('scope')
            ->orderBy('name')
            ->get()
            ->map(fn (OperationTemplate $template) => $this->present($template))
            ->values()
            ->all();
    }

    public function create(User $user, string $scope, ?int $squadronId, string $name, array $payload): OperationTemplate
    {
        return OperationTemplate::create([
            'name' => $name,
            'scope' => $scope,
            'owner_user_id' => $scope === OperationTemplate::SCOPE_PERSONAL ? $user->id : null,
            'squadron_id' => $scope === OperationTemplate::SCOPE_SQUADRON ? $squadronId : null,
            'created_by' => $user->id,
            'payload' => $this->sanitizePayload($payload),
        ]);
    }

    public function update(OperationTemplate $template, array $data): OperationTemplate
    {
        if (array_key_exists('name', $data)) {
            $template->name = $data['name'];
        }

        if (array_key_exists('payload', $data)) {
            $template->payload = $this->sanitizePayload($data['payload']);
        }

        $template->save();

        return $template->fresh();
    }

    public function present(OperationTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'scope' => $template->scope,
            'squadron_id' => $template->squadron_id,
            'payload' => $template->payload,
        ];
    }

    public function sanitizePayload(array $payload): array
    {
        if (! array_key_exists('operation_type', $payload) && array_key_exists('operation_kind', $payload)) {
            $payload['operation_type'] = $payload['operation_kind'];
        }

        if (! array_key_exists('gameplay_type', $payload) && array_key_exists('type', $payload)) {
            $payload['gameplay_type'] = $payload['type'];
        }

        if (! array_key_exists('extended_description', $payload) && array_key_exists('notes', $payload)) {
            $payload['extended_description'] = $payload['notes'];
        }

        $allowedKeys = [
            'title',
            'gameplay_type',
            'description',
            'extended_description',
            'visibility',
            'squadron_name',
            'operation_type',
            'branch',
            'operation_strictness',
            'start_location',
            'operation_location',
            'slots',
            'roles',
        ];

        $safe = array_intersect_key($payload, array_flip($allowedKeys));

        if (array_key_exists('slots', $safe)) {
            $safe['slots'] = is_array($safe['slots']) ? array_values($safe['slots']) : [];
        }

        if (array_key_exists('roles', $safe)) {
            $safe['roles'] = collect(is_array($safe['roles']) ? $safe['roles'] : [])
                ->map(function ($role) {
                    if (! is_array($role)) {
                        return null;
                    }

                    $displayName = trim((string) ($role['role_display_name'] ?? ''));
                    if ($displayName === '') {
                        return null;
                    }

                    $capacity = $role['capacity'] ?? null;

                    return [
                        'role_name' => trim((string) ($role['role_name'] ?? '')),
                        'role_display_name' => $displayName,
                        'capacity' => $capacity === '' || $capacity === null ? null : (int) $capacity,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            $safe['slots'] = collect($safe['roles'])
                ->pluck('role_display_name')
                ->values()
                ->all();
        }

        return $safe;
    }
}
