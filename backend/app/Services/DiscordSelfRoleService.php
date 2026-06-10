<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DiscordSelfRoleService
{
    public function settingsPayload(User $user, bool $forceRefresh = false): array
    {
        $definitions = $this->roleDefinitions();
        $selectedRoleIds = [];
        $status = 'ready';
        $statusMessage = 'Sync your Discord roles, then choose any branch and player roles you want active.';
        $syncedAt = null;

        if (! $user->discord_id) {
            $status = 'not_linked';
            $statusMessage = 'This account is not linked to a Discord profile yet.';
        } elseif (! $this->isConfigured()) {
            $status = 'not_configured';
            $statusMessage = 'Discord role sync is not configured on this environment yet.';
        } else {
            $snapshot = $this->memberSnapshot($user, $forceRefresh);
            $selectedRoleIds = $snapshot['role_ids'] ?? [];
            $syncedAt = $snapshot['synced_at'] ?? null;

            if (($snapshot['status'] ?? null) === 'not_in_guild') {
                $status = 'not_in_guild';
                $statusMessage = 'Discord says you are not currently an active member of the org guild.';
            } elseif (($snapshot['status'] ?? null) === 'unauthorized') {
                $status = 'unauthorized';
                $statusMessage = 'Discord rejected the configured bot token for role sync. The server needs a valid bot token before this can work.';
            } elseif (($snapshot['status'] ?? null) === 'forbidden') {
                $status = 'forbidden';
                $statusMessage = 'Discord denied the bot access to guild member roles. Check the bot permissions and intents for this server.';
            } elseif (($snapshot['status'] ?? null) === 'unavailable') {
                $status = 'unavailable';
                $statusMessage = 'Discord role sync is temporarily unavailable right now. Try syncing again in a minute.';
            }
        }

        $branchIds = array_column($definitions['branches'], 'id');
        $playerIds = array_column($definitions['player_roles'], 'id');

        return [
            'status' => $status,
            'status_message' => $statusMessage,
            'enabled' => $status !== 'not_configured',
            'discord_linked' => (bool) $user->discord_id,
            'discord_name' => $user->discord_name,
            'synced_at' => $syncedAt,
            'health_checks' => $this->healthChecks(
                $status,
                $user,
                $definitions,
                $statusMessage,
            ),
            'branch_roles' => array_map(
                fn (array $role) => [
                    ...$role,
                    'selected' => in_array($role['id'], $selectedRoleIds, true),
                ],
                $definitions['branches']
            ),
            'player_roles' => array_map(
                fn (array $role) => [
                    ...$role,
                    'selected' => in_array($role['id'], $selectedRoleIds, true),
                ],
                $definitions['player_roles']
            ),
            'selected_branch_role_ids' => array_values(array_filter(
                $branchIds,
                fn (string $roleId) => in_array($roleId, $selectedRoleIds, true)
            )),
            'selected_player_role_ids' => array_values(array_filter(
                $playerIds,
                fn (string $roleId) => in_array($roleId, $selectedRoleIds, true)
            )),
        ];
    }

    public function sync(User $user): array
    {
        if (! $user->discord_id) {
            throw ValidationException::withMessages([
                'discord_roles' => 'Link a Discord account before trying to sync guild roles.',
            ]);
        }

        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'discord_roles' => 'Discord role sync is not configured on this environment yet.',
            ]);
        }

        $snapshot = $this->fetchMemberSnapshot($user);
        $this->cacheSnapshot($user, $snapshot);

        if (($snapshot['status'] ?? null) === 'not_in_guild') {
            throw ValidationException::withMessages([
                'discord_roles' => 'Discord says you are not in the guild right now, so self-service roles cannot be synced yet.',
            ]);
        }

        if (($snapshot['status'] ?? null) === 'unauthorized') {
            throw ValidationException::withMessages([
                'discord_roles' => 'Discord rejected the configured bot token for role sync. Update the bot token on the server, then try again.',
            ]);
        }

        if (($snapshot['status'] ?? null) === 'forbidden') {
            throw ValidationException::withMessages([
                'discord_roles' => 'Discord denied the bot access to guild member roles. Check the bot permissions and intents, then try again.',
            ]);
        }

        if (($snapshot['status'] ?? null) !== 'ready') {
            throw ValidationException::withMessages([
                'discord_roles' => 'Discord role sync is temporarily unavailable right now.',
            ]);
        }

        return $this->settingsPayload($user, true);
    }

    public function update(User $user, array $branchRoleIds, array $playerRoleIds): array
    {
        if (! $user->discord_id) {
            throw ValidationException::withMessages([
                'discord_roles' => 'Link a Discord account before trying to update guild roles.',
            ]);
        }

        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'discord_roles' => 'Discord role sync is not configured on this environment yet.',
            ]);
        }

        $snapshot = $this->fetchMemberSnapshot($user);

        if (($snapshot['status'] ?? null) === 'not_in_guild') {
            throw ValidationException::withMessages([
                'discord_roles' => 'Discord says you are not in the guild right now, so self-service roles cannot be changed yet.',
            ]);
        }

        if (($snapshot['status'] ?? null) === 'unauthorized') {
            throw ValidationException::withMessages([
                'discord_roles' => 'Discord rejected the configured bot token for role sync. Update the bot token on the server, then try again.',
            ]);
        }

        if (($snapshot['status'] ?? null) === 'forbidden') {
            throw ValidationException::withMessages([
                'discord_roles' => 'Discord denied the bot access to guild member roles. Check the bot permissions and intents, then try again.',
            ]);
        }

        if (($snapshot['status'] ?? null) !== 'ready') {
            throw ValidationException::withMessages([
                'discord_roles' => 'Discord role sync is temporarily unavailable right now.',
            ]);
        }

        $definitions = $this->roleDefinitions();
        $branchIds = array_column($definitions['branches'], 'id');
        $playerIds = array_column($definitions['player_roles'], 'id');
        $selectedBranchIds = array_values(array_unique(array_map('strval', $branchRoleIds)));
        $selectedPlayerIds = array_values(array_unique(array_map('strval', $playerRoleIds)));
        $managedRoleIds = array_values(array_unique([...$branchIds, ...$playerIds]));
        $currentRoleIds = collect($snapshot['role_ids'] ?? [])
            ->map(fn ($roleId) => (string) $roleId)
            ->values();

        $nextRoleIds = $currentRoleIds
            ->reject(fn (string $roleId) => in_array($roleId, $managedRoleIds, true))
            ->merge([...$selectedBranchIds, ...$selectedPlayerIds])
            ->unique()
            ->values()
            ->all();

        $response = $this->client()->patch($this->memberEndpoint($user), [
            'roles' => $nextRoleIds,
        ]);

        if (! $response->successful()) {
            Log::warning('Discord self-role update failed', [
                'user_id' => $user->id,
                'discord_id' => $user->discord_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw ValidationException::withMessages([
                'discord_roles' => 'Discord did not accept that role update. Please try again in a moment.',
            ]);
        }

        $payload = $response->json();
        $updatedSnapshot = is_array($payload) && array_key_exists('roles', $payload)
            ? $this->normalizeSnapshot('ready', $payload['roles'] ?? [], now()->toIso8601String())
            : $this->normalizeSnapshot('ready', $nextRoleIds, now()->toIso8601String());

        $this->cacheSnapshot($user, $updatedSnapshot);

        return $this->settingsPayload($user, true);
    }

    public function roleDefinitions(): array
    {
        $groups = config('services.discord.self_assignable_roles', []);

        return [
            'branches' => $this->normalizeRoleGroup($groups['branches'] ?? []),
            'player_roles' => $this->normalizeRoleGroup($groups['player_roles'] ?? []),
        ];
    }

    protected function normalizeRoleGroup(array $roles): array
    {
        return collect($roles)
            ->map(function (array $role) {
                $id = trim((string) ($role['id'] ?? ''));
                $label = trim((string) ($role['label'] ?? ''));

                if ($id === '' || $label === '') {
                    return null;
                }

                return [
                    'id' => $id,
                    'label' => $label,
                ];
            })
            ->filter()
            ->unique('id')
            ->values()
            ->all();
    }

    protected function healthChecks(string $status, User $user, array $definitions, string $statusMessage): array
    {
        $branchCount = count($definitions['branches'] ?? []);
        $playerRoleCount = count($definitions['player_roles'] ?? []);
        $guildConfigured = filled(config('services.discord.guild_id'));
        $botTokenConfigured = filled(config('services.discord.bot_token'));

        return [
            [
                'key' => 'discord_link',
                'label' => 'Discord Linked',
                'ok' => (bool) $user->discord_id,
                'detail' => $user->discord_id
                    ? ($user->discord_name ?: 'Discord account linked')
                    : 'No Discord account is linked to this profile.',
            ],
            [
                'key' => 'guild_id',
                'label' => 'Guild Config',
                'ok' => $guildConfigured,
                'detail' => $guildConfigured
                    ? 'Guild id is loaded from the server config.'
                    : 'DISCORD_GUILD_ID is missing from the server config.',
            ],
            [
                'key' => 'bot_token',
                'label' => 'Bot Token',
                'ok' => $botTokenConfigured && $status !== 'unauthorized',
                'detail' => match (true) {
                    ! $botTokenConfigured => 'DISCORD_BOT_TOKEN is missing from the server config.',
                    $status === 'unauthorized' => 'Discord rejected the current bot token.',
                    default => 'Bot token is loaded and ready to use.',
                },
            ],
            [
                'key' => 'role_map',
                'label' => 'Role Map',
                'ok' => $branchCount > 0 && $playerRoleCount > 0,
                'detail' => sprintf(
                    '%d branch roles and %d player roles are configured for self-service sync.',
                    $branchCount,
                    $playerRoleCount
                ),
            ],
            [
                'key' => 'discord_api',
                'label' => 'Discord API',
                'ok' => in_array($status, ['ready', 'not_in_guild'], true),
                'detail' => $statusMessage,
            ],
        ];
    }

    protected function memberSnapshot(User $user, bool $forceRefresh = false): array
    {
        $cacheKey = $this->cacheKey($user);

        if (! $forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $snapshot = $this->fetchMemberSnapshot($user);
        $this->cacheSnapshot($user, $snapshot);

        return $snapshot;
    }

    protected function fetchMemberSnapshot(User $user): array
    {
        $response = $this->client()->get($this->memberEndpoint($user));

        if ($response->status() === 404) {
            return $this->normalizeSnapshot('not_in_guild', [], now()->toIso8601String());
        }

        if ($response->status() === 401) {
            Log::warning('Discord self-role sync returned unauthorized', [
                'user_id' => $user->id,
                'discord_id' => $user->discord_id,
                'status' => $response->status(),
            ]);

            return $this->normalizeSnapshot('unauthorized', [], null);
        }

        if ($response->status() === 403) {
            Log::warning('Discord self-role sync returned forbidden', [
                'user_id' => $user->id,
                'discord_id' => $user->discord_id,
                'status' => $response->status(),
            ]);

            return $this->normalizeSnapshot('forbidden', [], null);
        }

        if (! $response->successful()) {
            Log::warning('Discord self-role sync returned non-success', [
                'user_id' => $user->id,
                'discord_id' => $user->discord_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->normalizeSnapshot('unavailable', [], null);
        }

        $payload = $response->json();

        if (! is_array($payload) || ! array_key_exists('joined_at', $payload)) {
            Log::warning('Discord self-role sync returned an unexpected payload', [
                'user_id' => $user->id,
                'discord_id' => $user->discord_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->normalizeSnapshot('unavailable', [], null);
        }

        if (($payload['pending'] ?? false) === true) {
            return $this->normalizeSnapshot('not_in_guild', $payload['roles'] ?? [], now()->toIso8601String());
        }

        return $this->normalizeSnapshot('ready', $payload['roles'] ?? [], now()->toIso8601String());
    }

    protected function normalizeSnapshot(string $status, array $roleIds, ?string $syncedAt): array
    {
        return [
            'status' => $status,
            'role_ids' => collect($roleIds)
                ->map(fn ($roleId) => trim((string) $roleId))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'synced_at' => $syncedAt,
        ];
    }

    protected function cacheSnapshot(User $user, array $snapshot): void
    {
        Cache::put(
            $this->cacheKey($user),
            $snapshot,
            now()->addMinutes(max(1, (int) config('services.discord.role_sync_cache_minutes', 5)))
        );
    }

    protected function cacheKey(User $user): string
    {
        return sprintf(
            'discord_self_roles:%s:%s',
            (string) config('services.discord.guild_id'),
            (string) $user->discord_id
        );
    }

    protected function memberEndpoint(User $user): string
    {
        return sprintf(
            'guilds/%s/members/%s',
            config('services.discord.guild_id'),
            $user->discord_id
        );
    }

    protected function client()
    {
        return Http::baseUrl('https://discord.com/api/v10/')
            ->withToken((string) config('services.discord.bot_token'), 'Bot')
            ->acceptJson()
            ->timeout(8)
            ->connectTimeout(4)
            ->withHeaders([
                'User-Agent' => 'Horizon Interstellar Discord Self Roles',
            ]);
    }

    protected function isConfigured(): bool
    {
        $definitions = $this->roleDefinitions();

        return filled(config('services.discord.guild_id'))
            && filled(config('services.discord.bot_token'))
            && ! empty(array_merge($definitions['branches'], $definitions['player_roles']));
    }
}
