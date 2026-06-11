<?php

namespace App\Domain\Operations;

use App\Models\Operation;
use App\Models\Squadron;
use Illuminate\Support\Collection;

class OperationDiscordAnnouncementService
{
    public function buildAnnouncementPayload(Operation $operation, bool $ping = true): array
    {
        $operationLeader = $operation->creator?->rsi_handle
            ?? $operation->creator?->name;

        $payload = [
            'id' => $operation->id,
            'title' => $operation->title,
            'description' => $operation->description,
            'starts_at_discord' => $operation->starts_at ? "<t:{$operation->starts_at->timestamp}:f>" : null,
            'operation_type' => $operation->operation_type,
            'operation_strictness' => $operation->operation_strictness,
            'start_location' => $operation->start_location,
            'operation_leader' => $operationLeader,
            'operation_leader_discord_id' => $operation->creator?->discord_id,
            'operation_leader_discord_name' => $operation->creator?->discord_name,
            'operation_leader_discord_avatar' => $operation->creator?->discord_avatar,
            'ping' => $ping,
            'announce_to_default_channel' => blank($operation->squadron_id),
        ];

        if ($operation->squadron_name) {
            $payload['squadron_name'] = $operation->squadron_name;
        }

        $targets = $this->announcementTargets($operation);
        if ($targets !== []) {
            $payload['discord_targets'] = $targets;
        }

        return $payload;
    }

    public function deletePayload(Operation $operation): ?array
    {
        $targets = collect($operation->discord_message_targets ?? [])
            ->map(fn ($target) => $this->normalizeMessageTarget($target))
            ->filter()
            ->values()
            ->all();

        if ($targets !== []) {
            return [
                'operation_id' => $operation->id,
                'message_targets' => $targets,
            ];
        }

        $messageId = $this->normalizeDiscordId($operation->discord_message_id);

        if (! $messageId) {
            return null;
        }

        return [
            'operation_id' => $operation->id,
            'message_id' => $messageId,
        ];
    }

    public function persistAnnouncementResponse(Operation $operation, array $responseData): void
    {
        $targets = collect($responseData['message_targets'] ?? [])
            ->map(fn ($target) => $this->normalizeMessageTarget($target))
            ->filter()
            ->values()
            ->all();

        $primaryMessageId = $this->normalizeDiscordId($responseData['message_id'] ?? null);

        if (! $primaryMessageId && $targets !== []) {
            $primaryMessageId = $targets[0]['message_id'];
        }

        $operation->forceFill([
            'discord_message_id' => $primaryMessageId,
            'discord_message_targets' => $targets !== [] ? $targets : null,
        ])->saveQuietly();
    }

    public function clearAnnouncementTracking(Operation $operation): void
    {
        $operation->forceFill([
            'discord_message_id' => null,
            'discord_message_targets' => null,
        ])->saveQuietly();
    }

    protected function announcementTargets(Operation $operation): array
    {
        if (! $operation->squadron_id) {
            return [];
        }

        $selectedNames = $this->selectedSquadronNames($operation);

        $query = Squadron::query()
            ->select(['id', 'name', 'discord_channel_id'])
            ->whereNotNull('discord_channel_id');

        $query->where(function ($builder) use ($operation, $selectedNames) {
            $builder->whereKey($operation->squadron_id);

            if ($selectedNames !== []) {
                $builder->orWhereIn('name', $selectedNames);
            }
        });

        return $query->get()
            ->map(function (Squadron $squadron) {
                $channelId = $this->normalizeDiscordId($squadron->discord_channel_id);

                if (! $channelId) {
                    return null;
                }

                return [
                    'squadron_id' => $squadron->id,
                    'squadron_name' => $squadron->name,
                    'channel_id' => $channelId,
                ];
            })
            ->filter()
            ->unique('channel_id')
            ->values()
            ->all();
    }

    protected function selectedSquadronNames(Operation $operation): array
    {
        return collect(explode(',', (string) ($operation->squadron_name ?? '')))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeMessageTarget(mixed $target): ?array
    {
        if (! is_array($target)) {
            return null;
        }

        $channelId = $this->normalizeDiscordId($target['channel_id'] ?? null);
        $messageId = $this->normalizeDiscordId($target['message_id'] ?? null);

        if (! $channelId || ! $messageId) {
            return null;
        }

        return [
            'channel_id' => $channelId,
            'message_id' => $messageId,
        ];
    }

    protected function normalizeDiscordId(null|string|int $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        if ($string === '') {
            return null;
        }

        return preg_match('/^\d+$/', $string) === 1
            ? $string
            : null;
    }
}
