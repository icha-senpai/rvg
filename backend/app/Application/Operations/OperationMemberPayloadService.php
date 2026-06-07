<?php

namespace App\Application\Operations;

use App\Models\User;

class OperationMemberPayloadService
{
    public function verifiedMembers(): array
    {
        return User::query()
            ->select('id', 'rsi_handle', 'discord_name', 'discord_avatar', 'name')
            ->where('global_status', User::STATUS_ACTIVE)
            ->whereNotNull('rsi_verified_at')
            ->orderByRaw("LOWER(COALESCE(rsi_handle, discord_name, name, ''))")
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => $this->payload($user))
            ->values()
            ->all();
    }

    public function payloadsForIds(array $ids): array
    {
        $memberIds = collect($ids)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($memberIds->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->select('id', 'rsi_handle', 'discord_name', 'discord_avatar', 'name')
            ->whereIn('id', $memberIds->all())
            ->get()
            ->keyBy('id');

        return $memberIds
            ->map(fn (int $id) => $users->get($id))
            ->filter()
            ->map(fn (User $user) => $this->payload($user))
            ->values()
            ->all();
    }

    public function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'rsi_handle' => $user->rsi_handle,
            'discord_name' => $user->discord_name,
            'discord_avatar' => $user->discord_avatar,
            'name' => $user->name,
        ];
    }

    public function displayName(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return $user->rsi_handle
            ?? $user->discord_name
            ?? $user->name
            ?? "Member #{$user->id}";
    }
}
