<?php

namespace App\Domain\Operations\Services;

use App\Models\Operation;
use App\Models\OperationRole;
use Illuminate\Support\Str;

class OperationUpsertService
{
    public function prepare(array $data): array
    {
        [$normalized, $mediaId, $shouldSyncMedia] = $this->pullMediaId($this->applyDefaults($data));

        return [
            'data' => $normalized,
            'media_id' => $mediaId,
            'should_sync_media' => $shouldSyncMedia,
        ];
    }

    public function syncRoles(Operation $operation, array $data): void
    {
        if (! array_key_exists('roles', $data)) {
            return;
        }

        $incomingRoles = collect($data['roles'] ?? []);
        $existingRoles = $operation->roles()->get()->keyBy('id');
        $keptRoleIds = [];

        foreach ($incomingRoles as $index => $role) {
            $roleId = isset($role['id']) ? (int) $role['id'] : null;
            $roleName = trim((string) ($role['role_name'] ?? ''));
            $displayName = trim((string) ($role['role_display_name'] ?? ''));

            if ($displayName === '') {
                continue;
            }

            $payload = [
                'role_name' => $roleName !== '' ? $roleName : str($displayName)->lower()->slug('_')->value(),
                'role_display_name' => $displayName,
                'capacity' => $role['capacity'] ?? null,
                'sort_order' => $role['sort_order'] ?? $index,
                'is_required' => (bool) ($role['is_required'] ?? false),
            ];

            if ($roleId && $existingRoles->has($roleId)) {
                /** @var OperationRole $existingRole */
                $existingRole = $existingRoles->get($roleId);
                $previousDisplayName = $existingRole->role_display_name;
                $existingRole->fill($payload)->save();
                $keptRoleIds[] = $existingRole->id;

                if ($previousDisplayName !== $displayName) {
                    $operation->participants()
                        ->where('operation_role_id', $existingRole->id)
                        ->update(['slot' => $displayName]);
                }

                continue;
            }

            $createdRole = $operation->roles()->create($payload);
            $keptRoleIds[] = $createdRole->id;
        }

        $rolesToDelete = $existingRoles
            ->keys()
            ->reject(fn ($id) => in_array((int) $id, $keptRoleIds, true));

        if ($rolesToDelete->isNotEmpty()) {
            $operation->participants()
                ->whereIn('operation_role_id', $rolesToDelete->all())
                ->update([
                    'operation_role_id' => null,
                    'slot' => null,
                ]);

            $operation->roles()->whereIn('id', $rolesToDelete->all())->delete();
        }
    }

    protected function applyDefaults(array $data): array
    {
        $data['visibility'] = $data['visibility'] ?? 'open';

        if (array_key_exists('slots', $data) && empty($data['slots'])) {
            $data['slots'] = [];
        }

        if (array_key_exists('roles', $data)) {
            $data['roles'] = collect(is_array($data['roles']) ? $data['roles'] : [])
                ->map(function ($role, $index) {
                    if (! is_array($role)) {
                        return null;
                    }

                    $displayName = trim((string) ($role['role_display_name'] ?? ''));
                    if ($displayName === '') {
                        return null;
                    }

                    $capacity = $role['capacity'] ?? null;

                    return [
                        'id' => $role['id'] ?? null,
                        'role_name' => trim((string) ($role['role_name'] ?? '')),
                        'role_display_name' => $displayName,
                        'capacity' => $capacity === '' || $capacity === null ? null : (int) $capacity,
                        'sort_order' => isset($role['sort_order']) && $role['sort_order'] !== ''
                            ? (int) $role['sort_order']
                            : $index,
                        'is_required' => (bool) ($role['is_required'] ?? false),
                    ];
                })
                ->filter()
                ->values()
                ->all();

            $data['slots'] = collect($data['roles'])
                ->pluck('role_display_name')
                ->values()
                ->all();
        }

        return $data;
    }

    protected function pullMediaId(array $data): array
    {
        if (! array_key_exists('media_id', $data)) {
            return [$data, null, false];
        }

        $mediaId = $data['media_id'];
        unset($data['media_id']);

        return [$data, $mediaId, true];
    }
}
