<?php

namespace App\Domain\Squadrons;

use App\Models\Squadron;
use App\Models\SquadronMember;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Keeps squadron Discord channels and the shared squadron role aligned with the
 * authoritative Horizon squadron membership records.
 */
class SquadronDiscordService
{
    public const STATUS_NOT_LINKED = 'not_linked';
    public const STATUS_READY = 'ready';
    public const STATUS_REPAIR_NEEDED = 'repair_needed';

    /**
     * Save a manually entered Discord channel id and mark the squadron for
     * repair when the linked channel changes.
     */
    public function saveChannelConfiguration(Squadron $squadron, ?string $channelId): Squadron
    {
        $normalizedChannelId = $this->normalizeDiscordId($channelId);
        $previousChannelId = $this->normalizeDiscordId($squadron->discord_channel_id);

        $attributes = [
            'discord_channel_id' => $normalizedChannelId,
        ];

        if (! $normalizedChannelId) {
            $attributes += [
                'discord_sync_status' => self::STATUS_NOT_LINKED,
                'discord_last_synced_at' => null,
                'discord_sync_error' => null,
            ];
        } elseif ($previousChannelId !== $normalizedChannelId) {
            $attributes += [
                'discord_sync_status' => self::STATUS_REPAIR_NEEDED,
                'discord_last_synced_at' => null,
                'discord_sync_error' => null,
            ];
        }

        $squadron->update($attributes);

        return $squadron->fresh();
    }

    /**
     * Create a Discord text channel for the squadron, then sync the roster into
     * that channel.
     */
    public function createChannelAndSync(Squadron $squadron): array
    {
        $configurationError = $this->createChannelConfigurationError();
        if ($configurationError) {
            return $this->markRepairNeeded($squadron, $configurationError);
        }

        $response = $this->postToBot('/squadrons/channel/create', [
            'squadron_id' => $squadron->id,
            'squadron_name' => $squadron->name,
            'channel_name' => $this->channelNameFor($squadron),
            'category_id' => config('services.discord.squadron_category_id'),
            'shared_role_id' => config('services.discord.squadron_shared_role_id'),
        ]);

        if (! $response['ok']) {
            return $this->markRepairNeeded($squadron, $response['message']);
        }

        $channelId = $this->normalizeDiscordId($response['data']['channel_id'] ?? null);
        if (! $channelId) {
            return $this->markRepairNeeded($squadron, 'Discord created a channel but did not return a usable channel id.');
        }

        $squadron = $this->saveChannelConfiguration($squadron, $channelId);

        $channelResult = $this->syncSquadronChannelAccess($squadron);
        if (! $channelResult['ok']) {
            return $channelResult;
        }

        $sharedRoleResult = $this->syncSharedRoleForUsers($this->activeMemberDiscordIdsForSquadron($squadron));
        if (! $sharedRoleResult['ok']) {
            return $this->markRepairNeeded($squadron, $sharedRoleResult['message']);
        }

        return $channelResult;
    }

    /**
     * Delete the linked Discord text channel for this squadron when the admin
     * explicitly opts into that destructive action.
     */
    public function deleteLinkedChannel(Squadron $squadron): array
    {
        $squadron->refresh();

        $channelId = $this->normalizeDiscordId($squadron->discord_channel_id);
        if (! $channelId) {
            return [
                'ok' => true,
                'message' => 'No linked Discord channel needed deletion.',
                'squadron' => $squadron,
            ];
        }

        $configurationError = $this->baseConfigurationError();
        if ($configurationError) {
            return [
                'ok' => false,
                'message' => $configurationError,
                'squadron' => $squadron,
            ];
        }

        $response = $this->postToBot('/squadrons/channel/delete', [
            'squadron_id' => $squadron->id,
            'squadron_name' => $squadron->name,
            'discord_channel_id' => $channelId,
        ]);

        if (! $response['ok']) {
            return [
                'ok' => false,
                'message' => $response['message'],
                'squadron' => $squadron,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Linked Discord channel deleted.',
            'squadron' => $squadron,
        ];
    }

    /**
     * Collect the Discord users who may need their shared Squadron role
     * re-evaluated after this squadron is deleted.
     */
    public function discordIdsAffectedBySquadronDeletion(Squadron $squadron): array
    {
        $memberDiscordIds = SquadronMember::query()
            ->where('squadron_id', $squadron->id)
            ->whereHas('user', fn ($query) => $query->whereNotNull('discord_id'))
            ->with('user:id,discord_id')
            ->get()
            ->pluck('user.discord_id')
            ->filter()
            ->map(fn ($discordId) => (string) $discordId)
            ->unique();

        $leaderDiscordId = $this->normalizeDiscordId($squadron->leader()->value('discord_id'));

        return $memberDiscordIds
            ->push($leaderDiscordId)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Sync only this squadron's text channel overwrites to Discord.
     */
    public function syncSquadronChannelAccess(Squadron $squadron): array
    {
        $squadron->refresh();

        $configurationError = $this->channelSyncConfigurationError();
        if ($configurationError) {
            return $this->markRepairNeeded($squadron, $configurationError);
        }

        $channelId = $this->normalizeDiscordId($squadron->discord_channel_id);
        if (! $channelId) {
            return [
                'ok' => false,
                'status' => self::STATUS_NOT_LINKED,
                'message' => 'Discord channel not linked yet.',
                'squadron' => $this->saveChannelConfiguration($squadron, null),
            ];
        }

        $response = $this->postToBot('/squadrons/channel/sync', [
            'squadron_id' => $squadron->id,
            'squadron_name' => $squadron->name,
            'discord_channel_id' => $channelId,
            'shared_role_id' => config('services.discord.squadron_shared_role_id'),
            'active_member_discord_ids' => $this->activeMemberDiscordIdsForSquadron($squadron),
        ]);

        if (! $response['ok']) {
            return $this->markRepairNeeded($squadron, $response['message']);
        }

        $squadron->update([
            'discord_sync_status' => self::STATUS_READY,
            'discord_last_synced_at' => now(),
            'discord_sync_error' => null,
        ]);

        return [
            'ok' => true,
            'status' => self::STATUS_READY,
            'message' => 'Discord squadron channel access synced.',
            'squadron' => $squadron->fresh(),
        ];
    }

    /**
     * Reconcile the shared Squadron role for only the provided Discord users.
     */
    public function syncSharedRoleForUsers(iterable $discordIds): array
    {
        $configurationError = $this->sharedRoleConfigurationError();
        if ($configurationError) {
            return [
                'ok' => false,
                'message' => $configurationError,
                'data' => [],
            ];
        }

        $targetDiscordIds = collect($discordIds)
            ->map(fn ($value) => $this->normalizeDiscordId($value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($targetDiscordIds === []) {
            return [
                'ok' => true,
                'message' => 'No Discord users needed shared Squadron role reconciliation.',
                'data' => [
                    'added_shared_role_count' => 0,
                    'removed_shared_role_count' => 0,
                    'missing_guild_member_ids' => [],
                    'shared_role_add_failed_ids' => [],
                    'shared_role_remove_failed_ids' => [],
                ],
            ];
        }

        $response = $this->postToBot('/squadrons/shared-role/sync-members', [
            'shared_role_id' => config('services.discord.squadron_shared_role_id'),
            'member_discord_ids' => $targetDiscordIds,
            'active_squadron_member_discord_ids' => $this->globalActiveSquadronMemberDiscordIds(),
        ]);

        return $this->sharedRoleResponseResult($response);
    }

    /**
     * Re-run the shared Squadron role check for users affected by a squadron
     * deletion after the squadron rows have been removed from Horizon.
     */
    public function syncSharedRoleAfterSquadronDeletion(iterable $discordIds): array
    {
        return $this->syncSharedRoleForUsers($discordIds);
    }

    /**
     * Grant the Discord lieutenant role and announce the promotion in the
     * squadron's linked channel when possible.
     */
    public function syncLieutenantPromotion(Squadron $squadron, string $memberDiscordId): void
    {
        $memberDiscordId = $this->normalizeDiscordId($memberDiscordId) ?? '';
        if ($memberDiscordId === '') {
            return;
        }

        $configurationError = $this->lieutenantConfigurationError();
        if ($configurationError) {
            Log::warning('Squadron lieutenant Discord promotion skipped', [
                'squadron_id' => $squadron->id,
                'member_discord_id' => $memberDiscordId,
                'error' => $configurationError,
            ]);

            return;
        }

        $response = $this->postToBot('/squadrons/lieutenant/promote', [
            'squadron_id' => $squadron->id,
            'squadron_name' => $squadron->name,
            'member_discord_id' => $memberDiscordId,
            'lieutenant_role_id' => config('services.discord.squadron_lieutenant_role_id'),
            'discord_channel_id' => $this->normalizeDiscordId($squadron->discord_channel_id),
            'announcement_message' => "Please congratulate <@{$memberDiscordId}> on their promotion to Lieutenant.",
        ]);

        if (! $response['ok']) {
            Log::warning('Squadron lieutenant Discord promotion failed', [
                'squadron_id' => $squadron->id,
                'member_discord_id' => $memberDiscordId,
                'error' => $response['message'],
            ]);
        }
    }

    /**
     * Remove the Discord lieutenant role when a squadron lieutenant is demoted.
     */
    public function syncLieutenantDemotion(string $memberDiscordId): void
    {
        $memberDiscordId = $this->normalizeDiscordId($memberDiscordId) ?? '';
        if ($memberDiscordId === '') {
            return;
        }

        $configurationError = $this->lieutenantConfigurationError();
        if ($configurationError) {
            Log::warning('Squadron lieutenant Discord demotion skipped', [
                'member_discord_id' => $memberDiscordId,
                'error' => $configurationError,
            ]);

            return;
        }

        $response = $this->postToBot('/squadrons/lieutenant/demote', [
            'member_discord_id' => $memberDiscordId,
            'lieutenant_role_id' => config('services.discord.squadron_lieutenant_role_id'),
        ]);

        if (! $response['ok']) {
            Log::warning('Squadron lieutenant Discord demotion failed', [
                'member_discord_id' => $memberDiscordId,
                'error' => $response['message'],
            ]);
        }
    }

    /**
     * Run a full shared-role repair against the guild so stale holders are
     * removed even if they are not part of the changed squadron.
     */
    public function repairSharedSquadronRole(): array
    {
        $configurationError = $this->sharedRoleConfigurationError();
        if ($configurationError) {
            return [
                'ok' => false,
                'message' => $configurationError,
                'data' => [],
            ];
        }

        $response = $this->postToBot('/squadrons/shared-role/repair', [
            'shared_role_id' => config('services.discord.squadron_shared_role_id'),
            'active_squadron_member_discord_ids' => $this->globalActiveSquadronMemberDiscordIds(),
        ]);

        return $this->sharedRoleResponseResult($response);
    }

    /**
     * Run best-effort Discord upkeep after membership changes without blocking
     * the originating Horizon workflow when Discord is unavailable.
     */
    public function syncAfterMembershipChange(Squadron $squadron, iterable $affectedDiscordIds = []): void
    {
        try {
            $squadron->refresh();

            if ($this->normalizeDiscordId($squadron->discord_channel_id)) {
                $channelResult = $this->syncSquadronChannelAccess($squadron);
                if (! $channelResult['ok']) {
                    throw new \RuntimeException($channelResult['message'] ?? 'Squadron channel sync failed.');
                }
            }

            $sharedRoleResult = $this->syncSharedRoleForUsers($affectedDiscordIds);
            if (! $sharedRoleResult['ok']) {
                throw new \RuntimeException($sharedRoleResult['message'] ?? 'Shared Squadron role sync failed.');
            }
        } catch (\Throwable $e) {
            Log::warning('Squadron Discord sync failed after membership change', [
                'squadron_id' => $squadron->id,
                'error' => $e->getMessage(),
            ]);

            $this->markRepairNeeded($squadron, $e->getMessage());
        }
    }

    /**
     * Normalize the admin save flow: auto-create when requested, sync when a
     * channel is linked, otherwise leave the squadron in not-linked state.
     */
    public function syncAfterAdminSave(Squadron $squadron, bool $createChannelIfMissing = false): array
    {
        $squadron->refresh();

        if (! $this->normalizeDiscordId($squadron->discord_channel_id) && $createChannelIfMissing) {
            return $this->createChannelAndSync($squadron);
        }

        if (! $this->normalizeDiscordId($squadron->discord_channel_id)) {
            $sharedRoleResult = $this->syncSharedRoleForUsers($this->activeMemberDiscordIdsForSquadron($squadron));

            if (! $sharedRoleResult['ok']) {
                return $this->markRepairNeeded($squadron, $sharedRoleResult['message']);
            }

            return [
                'ok' => false,
                'status' => self::STATUS_NOT_LINKED,
                'message' => 'Discord channel not linked yet.',
                'squadron' => $this->saveChannelConfiguration($squadron, null),
            ];
        }

        $channelResult = $this->syncSquadronChannelAccess($squadron);
        if (! $channelResult['ok']) {
            return $channelResult;
        }

        $sharedRoleResult = $this->syncSharedRoleForUsers($this->activeMemberDiscordIdsForSquadron($squadron));
        if (! $sharedRoleResult['ok']) {
            return $this->markRepairNeeded($squadron, $sharedRoleResult['message']);
        }

        return $channelResult;
    }

    protected function activeMemberDiscordIdsForSquadron(Squadron $squadron): array
    {
        return SquadronMember::query()
            ->where('squadron_id', $squadron->id)
            ->active()
            ->whereHas('user', fn ($query) => $query->whereNotNull('discord_id'))
            ->with('user:id,discord_id')
            ->get()
            ->pluck('user.discord_id')
            ->filter()
            ->map(fn ($discordId) => (string) $discordId)
            ->unique()
            ->values()
            ->all();
    }

    protected function globalActiveSquadronMemberDiscordIds(): array
    {
        return SquadronMember::query()
            ->active()
            ->whereHas('user', fn ($query) => $query->whereNotNull('discord_id'))
            ->with('user:id,discord_id')
            ->get()
            ->pluck('user.discord_id')
            ->filter()
            ->map(fn ($discordId) => (string) $discordId)
            ->unique()
            ->values()
            ->all();
    }

    protected function channelNameFor(Squadron $squadron): string
    {
        return (string) Str::of($squadron->name)
            ->lower()
            ->slug('-');
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

    protected function createChannelConfigurationError(): ?string
    {
        return $this->channelSyncConfigurationError()
            ?? (! filled(config('services.discord.squadron_category_id'))
                ? 'Discord squadron category id is not configured yet.'
                : null);
    }

    protected function channelSyncConfigurationError(): ?string
    {
        return $this->baseConfigurationError()
            ?? $this->sharedRoleConfigurationError();
    }

    protected function sharedRoleConfigurationError(): ?string
    {
        return $this->baseConfigurationError()
            ?? (! filled(config('services.discord.squadron_shared_role_id'))
                ? 'Discord squadron shared role id is not configured yet.'
                : null);
    }

    protected function lieutenantConfigurationError(): ?string
    {
        return $this->baseConfigurationError()
            ?? (! filled(config('services.discord.squadron_lieutenant_role_id'))
                ? 'Discord squadron lieutenant role id is not configured yet.'
                : null);
    }

    protected function baseConfigurationError(): ?string
    {
        if (! filled(config('services.bot.url'))) {
            return 'Discord bot webhook URL is not configured yet.';
        }

        if (! filled(config('services.bot.secret'))) {
            return 'Discord bot secret is not configured yet.';
        }

        return null;
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
            Log::warning('Squadron Discord bot request failed', [
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

            Log::warning('Squadron Discord bot request returned an error', [
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

    protected function markRepairNeeded(Squadron $squadron, string $message): array
    {
        $squadron->update([
            'discord_sync_status' => self::STATUS_REPAIR_NEEDED,
            'discord_sync_error' => Str::limit(trim($message), 1000, ''),
        ]);

        return [
            'ok' => false,
            'status' => self::STATUS_REPAIR_NEEDED,
            'message' => $message,
            'squadron' => $squadron->fresh(),
        ];
    }

    protected function sharedRoleResponseResult(array $response): array
    {
        if (! $response['ok']) {
            return $response;
        }

        $missingGuildMemberIds = collect($response['data']['missing_guild_member_ids'] ?? [])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->values();

        $sharedRoleAddFailedIds = collect($response['data']['shared_role_add_failed_ids'] ?? [])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->values();

        $sharedRoleRemoveFailedIds = collect($response['data']['shared_role_remove_failed_ids'] ?? [])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->values();

        if ($missingGuildMemberIds->isEmpty() && $sharedRoleAddFailedIds->isEmpty() && $sharedRoleRemoveFailedIds->isEmpty()) {
            return $response;
        }

        $issues = [];

        if ($missingGuildMemberIds->isNotEmpty()) {
            $issues[] = 'Missing guild members: ' . $missingGuildMemberIds->implode(', ');
        }

        if ($sharedRoleAddFailedIds->isNotEmpty()) {
            $issues[] = 'Shared role add failed for: ' . $sharedRoleAddFailedIds->implode(', ');
        }

        if ($sharedRoleRemoveFailedIds->isNotEmpty()) {
            $issues[] = 'Shared role removal failed for: ' . $sharedRoleRemoveFailedIds->implode(', ');
        }

        return [
            'ok' => false,
            'message' => implode(' | ', $issues),
            'data' => $response['data'] ?? [],
        ];
    }
}
