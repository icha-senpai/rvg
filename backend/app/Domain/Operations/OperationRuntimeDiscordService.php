<?php

namespace App\Domain\Operations;

use App\Models\Operation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OperationRuntimeDiscordService
{
    public function syncOperationChannels(Operation $operation, array $channels, array $deletedChannelIds = []): array
    {
        $configurationError = $this->operationChannelConfigurationError();
        if ($configurationError) {
            return [
                'ok' => false,
                'message' => $configurationError,
                'data' => [],
            ];
        }

        $response = $this->postToBot('/operations/runtime/sync-channels', [
            'operation_id' => $operation->id,
            'operation_title' => $operation->title,
            'category_id' => config('services.discord.operation_category_id'),
            'member_role_id' => config('services.discord.operation_member_role_id'),
            'channels' => $channels,
            'delete_channel_ids' => $deletedChannelIds,
        ]);

        if (! $response['ok']) {
            return $response;
        }

        return [
            'ok' => true,
            'message' => $response['message'],
            'data' => [
                'channels' => is_array($response['data']['channels'] ?? null)
                    ? $response['data']['channels']
                    : [],
                'moved_member_count' => (int) ($response['data']['moved_member_count'] ?? 0),
                'created_channel_count' => (int) ($response['data']['created_channel_count'] ?? 0),
                'renamed_channel_count' => (int) ($response['data']['renamed_channel_count'] ?? 0),
                'deleted_channel_count' => (int) ($response['data']['deleted_channel_count'] ?? 0),
                'missing_guild_member_ids' => collect($response['data']['missing_guild_member_ids'] ?? [])
                    ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                    ->values()
                    ->all(),
            ],
        ];
    }

    public function syncLobby(Operation $operation): array
    {
        $configurationError = $this->configurationError();
        if ($configurationError) {
            return [
                'ok' => false,
                'message' => $configurationError,
                'data' => [],
            ];
        }

        $response = $this->postToBot('/operations/runtime/sync-lobby', [
            'operation_id' => $operation->id,
            'operation_title' => $operation->title,
            'lobby_channel_ids' => $this->lobbyChannelIds(),
        ]);

        if (! $response['ok']) {
            return $response;
        }

        return [
            'ok' => true,
            'message' => $response['message'],
            'data' => [
                'source_channel_ids' => collect($response['data']['source_channel_ids'] ?? [])
                    ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                    ->values()
                    ->all(),
                'present_discord_ids' => collect($response['data']['present_discord_ids'] ?? [])
                    ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                    ->values()
                    ->all(),
                'present_by_channel' => is_array($response['data']['present_by_channel'] ?? null)
                    ? $response['data']['present_by_channel']
                    : [],
            ],
        ];
    }

    public function lobbyChannelIds(): array
    {
        return collect([
            config('services.discord.operation_lobby_1_channel_id'),
            config('services.discord.operation_lobby_2_channel_id'),
        ])
            ->map(fn ($value) => $this->normalizeDiscordId($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function configurationError(): ?string
    {
        if (! filled(config('services.bot.url'))) {
            return 'Discord bot webhook URL is not configured yet.';
        }

        if (! filled(config('services.bot.secret'))) {
            return 'Discord bot secret is not configured yet.';
        }

        if ($this->lobbyChannelIds() === []) {
            return 'Operation lobby channel ids are not configured yet.';
        }

        return null;
    }

    protected function operationChannelConfigurationError(): ?string
    {
        return $this->configurationError()
            ?? (! filled(config('services.discord.operation_category_id'))
                ? 'Operation Discord category id is not configured yet.'
                : null)
            ?? (! filled(config('services.discord.operation_member_role_id'))
                ? 'Operation Discord member role id is not configured yet.'
                : null);
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

    protected function postToBot(string $path, array $payload): array
    {
        try {
            $response = Http::asJson()
                ->timeout(20)
                ->withHeaders([
                    'X-Bot-Secret' => (string) config('services.bot.secret'),
                ])
                ->post(rtrim((string) config('services.bot.url'), '/') . $path, $payload);
        } catch (\Throwable $e) {
            Log::warning('Operation runtime Discord bot request failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Discord bot request failed: ' . $e->getMessage(),
                'data' => [],
            ];
        }

        if (! $response->successful()) {
            $message = $response->json('message');
            if (! is_string($message) || trim($message) === '') {
                $message = 'Discord bot rejected the request (' . $response->status() . ').';
            }

            Log::warning('Operation runtime Discord bot request returned an error', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'ok' => false,
                'message' => $message,
                'data' => $response->json() ?: [],
            ];
        }

        $data = $response->json();

        return [
            'ok' => true,
            'message' => is_string($data['message'] ?? null) ? $data['message'] : 'OK',
            'data' => is_array($data) ? $data : [],
        ];
    }
}
