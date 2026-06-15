<?php

namespace App\Domain\Operations\Services;

use App\Domain\Operations\OperationRuntimeDiscordService;
use App\Models\Operation;
use App\Models\OperationDiscordChannel;
use App\Models\OperationParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationRuntimeService
{
    public function __construct(
        protected OperationRuntimeDiscordService $discord
    ) {}

    public function build(Operation $operation): array
    {
        $operation->loadMissing([
            'participants.user',
            'participants.role',
            'participants.discordChannel',
            'discordChannels',
            'syncRuns.syncedBy:id,name,rsi_handle,discord_name',
        ]);

        $participants = $operation->participants->values();
        $activeParticipants = $participants
            ->reject(fn (OperationParticipant $participant) => $participant->isSignedOffBeforeStart())
            ->values();

        return [
            'lobby_channel_ids' => $this->discord->lobbyChannelIds(),
            'summary' => [
                'active_participant_count' => $activeParticipants->count(),
                'present_count' => $activeParticipants->filter(fn (OperationParticipant $participant) => $participant->synced_in_at !== null)->count(),
                'no_show_count' => $participants->filter(fn (OperationParticipant $participant) => $participant->runtime_status === OperationParticipant::RUNTIME_STATUS_NO_SHOW)->count(),
                'excused_count' => $participants->filter(fn (OperationParticipant $participant) => $participant->runtime_status === OperationParticipant::RUNTIME_STATUS_EXCUSED)->count(),
                'signed_off_count' => $participants->filter(fn (OperationParticipant $participant) => $participant->isSignedOffBeforeStart())->count(),
                'walk_in_count' => $activeParticipants
                    ->filter(fn (OperationParticipant $participant) => $participant->runtime_source === OperationParticipant::RUNTIME_SOURCE_WALK_IN)
                    ->count(),
                'missing_discord_link_count' => $activeParticipants
                    ->filter(fn (OperationParticipant $participant) => blank($participant->user?->discord_id))
                    ->count(),
            ],
            'last_sync_run' => $this->syncRunPayload($operation->syncRuns->first()),
            'sync_runs' => $operation->syncRuns
                ->take(10)
                ->map(fn ($syncRun) => $this->syncRunPayload($syncRun))
                ->filter()
                ->values()
                ->all(),
            'participants' => $participants
                ->map(fn (OperationParticipant $participant) => $this->participantPayload($participant))
                ->values()
                ->all(),
            'discord_channels' => $operation->discordChannels
                ->map(fn (OperationDiscordChannel $channel) => $this->discordChannelPayload($channel))
                ->values()
                ->all(),
        ];
    }

    public function saveLayout(Operation $operation, array $channels, array $participantAssignments = []): array
    {
        return DB::transaction(function () use ($operation, $channels, $participantAssignments) {
            $operation->loadMissing([
                'participants.user',
                'participants.role',
                'participants.discordChannel',
                'discordChannels',
                'syncRuns.syncedBy:id,name,rsi_handle,discord_name',
            ]);

            $existingChannels = $operation->discordChannels->keyBy('id');
            $channelKeyToId = [];
            $keptChannelIds = [];

            foreach (array_values($channels) as $index => $channelData) {
                $channelId = isset($channelData['id']) && is_numeric($channelData['id'])
                    ? (int) $channelData['id']
                    : null;
                $clientKey = trim((string) ($channelData['client_key'] ?? ''));
                $name = trim((string) ($channelData['name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $channel = $channelId ? $existingChannels->get($channelId) : null;

                if (! $channel) {
                    $channel = $operation->discordChannels()->create([
                        'name' => $name,
                        'sort_order' => $index,
                    ]);
                } else {
                    $channel->forceFill([
                        'name' => $name,
                        'sort_order' => $index,
                    ])->save();
                }

                $keptChannelIds[] = (int) $channel->id;
                $channelKeyToId[(string) $channel->id] = (int) $channel->id;

                if ($clientKey !== '') {
                    $channelKeyToId[$clientKey] = (int) $channel->id;
                }
            }

            $removedChannelIds = $existingChannels->keys()
                ->map(fn ($value) => (int) $value)
                ->reject(fn (int $id) => in_array($id, $keptChannelIds, true))
                ->values();

            if ($removedChannelIds->isNotEmpty()) {
                $operation->participants()
                    ->whereIn('operation_discord_channel_id', $removedChannelIds->all())
                    ->update(['operation_discord_channel_id' => null]);

                $operation->discordChannels()
                    ->whereIn('id', $removedChannelIds->all())
                    ->delete();
            }

            $participantsById = $operation->participants->keyBy('id');

            foreach ($participantAssignments as $assignment) {
                $participantId = isset($assignment['participant_id']) && is_numeric($assignment['participant_id'])
                    ? (int) $assignment['participant_id']
                    : null;
                $channelKey = trim((string) ($assignment['channel_key'] ?? ''));

                if (! $participantId || ! $participantsById->has($participantId)) {
                    continue;
                }

                $participant = $participantsById->get($participantId);
                $participant->operation_discord_channel_id = $channelKey !== '' && isset($channelKeyToId[$channelKey])
                    ? $channelKeyToId[$channelKey]
                    : null;
                $participant->save();
            }

            return $this->build($operation->fresh([
                'participants.user',
                'participants.role',
                'participants.discordChannel',
                'discordChannels',
                'syncRuns.syncedBy:id,name,rsi_handle,discord_name',
            ]));
        });
    }

    public function syncLobby(Operation $operation, User $actor): array
    {
        $this->ensureRuntimeAvailable($operation);

        $discordResult = $this->discord->syncLobby($operation);
        if (! $discordResult['ok']) {
            throw ValidationException::withMessages([
                'discord' => $discordResult['message'],
            ]);
        }

        $presentDiscordIds = collect($discordResult['data']['present_discord_ids'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        $matchedUsers = User::query()
            ->whereIn('discord_id', $presentDiscordIds->all())
            ->get(['id', 'discord_id'])
            ->keyBy(fn (User $user) => (string) $user->discord_id);

        $matchedPresentUserIds = $matchedUsers
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $unmatchedDiscordIds = $presentDiscordIds
            ->reject(fn (string $discordId) => $matchedUsers->has($discordId))
            ->values()
            ->all();

        return DB::transaction(function () use ($operation, $actor, $discordResult, $matchedUsers, $matchedPresentUserIds, $unmatchedDiscordIds) {
            $timestamp = now();

            $operation->load([
                'participants.user',
                'participants.role',
                'participants.discordChannel',
                'discordChannels',
                'syncRuns.syncedBy:id,name,rsi_handle,discord_name',
            ]);

            $participantsByUserId = $operation->participants
                ->filter(fn (OperationParticipant $participant) => filled($participant->user_id))
                ->keyBy(fn (OperationParticipant $participant) => (int) $participant->user_id);

            $activeRoster = $operation->participants
                ->filter(fn (OperationParticipant $participant) => filled($participant->user_id) && ! $participant->isSignedOffBeforeStart())
                ->keyBy(fn (OperationParticipant $participant) => (int) $participant->user_id);

            $noShowUserIds = [];
            $walkInUserIds = [];

            foreach ($activeRoster as $participant) {
                $participantUserDiscordId = trim((string) ($participant->user?->discord_id ?? ''));

                if ($participantUserDiscordId === '') {
                    $participant->forceFill([
                        'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
                        'runtime_source' => $participant->runtime_source ?: OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
                    ])->save();

                    continue;
                }

                if (in_array((int) $participant->user_id, $matchedPresentUserIds, true)) {
                    if ($this->shouldPreserveSyncedRuntimeStatus($participant)) {
                        $participant->forceFill([
                            'attendance_status' => $participant->attendance_status ?: 'signed_up',
                            'signed_off_at' => null,
                            'synced_in_at' => $participant->synced_in_at ?: $timestamp,
                        ])->save();

                        continue;
                    }

                    $participant->forceFill([
                        'attendance_status' => 'signed_up',
                        'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
                        'runtime_source' => $participant->runtime_source ?: OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
                        'signed_off_at' => null,
                        'synced_in_at' => $timestamp,
                    ])->save();

                    continue;
                }

                if ($this->shouldPreserveAbsentRuntimeStatus($participant)) {
                    continue;
                }

                $participant->forceFill([
                    'runtime_status' => OperationParticipant::RUNTIME_STATUS_NO_SHOW,
                    'runtime_source' => $participant->runtime_source ?: OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
                    'synced_in_at' => null,
                ])->save();

                $noShowUserIds[] = (int) $participant->user_id;
            }

            foreach ($matchedUsers as $matchedUser) {
                if ($activeRoster->has((int) $matchedUser->id)) {
                    continue;
                }

                $existingParticipant = $participantsByUserId->get((int) $matchedUser->id);

                if ($existingParticipant) {
                    $existingParticipant->forceFill([
                        'attendance_status' => 'signed_up',
                        'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
                        'runtime_source' => $existingParticipant->runtime_source ?: OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
                        'signed_off_at' => null,
                        'synced_in_at' => $timestamp,
                    ])->save();

                    continue;
                }

                $operation->participants()->create([
                    'user_id' => $matchedUser->id,
                    'attendance_status' => 'signed_up',
                    'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
                    'runtime_source' => OperationParticipant::RUNTIME_SOURCE_WALK_IN,
                    'synced_in_at' => $timestamp,
                    'notes' => 'Walk-in added from Discord lobby sync.',
                    'operation_discord_channel_id' => null,
                ]);

                $walkInUserIds[] = (int) $matchedUser->id;
            }

            $syncRun = $operation->syncRuns()->create([
                'synced_by_user_id' => $actor->id,
                'synced_at' => $timestamp,
                'source_channel_ids' => $discordResult['data']['source_channel_ids'] ?? [],
                'present_user_ids' => $matchedPresentUserIds,
                'no_show_user_ids' => array_values(array_unique($noShowUserIds)),
                'walk_in_user_ids' => array_values(array_unique($walkInUserIds)),
            ]);

            return [
                'present_count' => count($matchedPresentUserIds),
                'no_show_count' => count(array_unique($noShowUserIds)),
                'walk_in_count' => count(array_unique($walkInUserIds)),
                'unmatched_discord_ids' => $unmatchedDiscordIds,
                'sync_run' => $this->syncRunPayload($syncRun->fresh('syncedBy:id,name,rsi_handle,discord_name')),
                'runtime' => $this->build($operation->fresh([
                    'participants.user',
                    'participants.role',
                    'participants.discordChannel',
                    'discordChannels',
                    'syncRuns.syncedBy:id,name,rsi_handle,discord_name',
                ])),
            ];
        });
    }

    public function updateParticipantStatus(Operation $operation, OperationParticipant $participant, string $status): array
    {
        $this->ensureRuntimeAvailable($operation);
        $this->ensureParticipantBelongsToOperation($operation, $participant);

        return DB::transaction(function () use ($operation, $participant, $status) {
            $participant->forceFill($this->participantStatusUpdates($participant, $status))->save();

            return $this->build($operation->fresh([
                'participants.user',
                'participants.role',
                'participants.discordChannel',
                'discordChannels',
                'syncRuns.syncedBy:id,name,rsi_handle,discord_name',
            ]));
        });
    }

    public function addWalkIn(Operation $operation, User $actor, User $user): array
    {
        $this->ensureRuntimeAvailable($operation);

        return DB::transaction(function () use ($operation, $actor, $user) {
            $existingParticipant = $operation->participants()
                ->where('user_id', $user->id)
                ->first();

            if ($existingParticipant && ! $existingParticipant->isSignedOffBeforeStart()) {
                throw ValidationException::withMessages([
                    'user_id' => 'That member is already on the live roster.',
                ]);
            }

            if ($existingParticipant) {
                $existingParticipant->forceFill([
                    'attendance_status' => 'signed_up',
                    'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
                    'runtime_source' => $existingParticipant->runtime_source ?: OperationParticipant::RUNTIME_SOURCE_MANUAL,
                    'signed_off_at' => null,
                    'synced_in_at' => now(),
                ])->save();
            } else {
                $operation->participants()->create([
                    'user_id' => $user->id,
                    'attendance_status' => 'signed_up',
                    'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
                    'runtime_source' => OperationParticipant::RUNTIME_SOURCE_WALK_IN,
                    'synced_in_at' => now(),
                    'notes' => 'Walk-in added from the operation run tool.',
                    'operation_discord_channel_id' => null,
                ]);
            }

            $this->recordManualWalkInOnLatestSync($operation, $actor, $user);

            return $this->build($operation->fresh([
                'participants.user',
                'participants.role',
                'participants.discordChannel',
                'discordChannels',
                'syncRuns.syncedBy:id,name,rsi_handle,discord_name',
            ]));
        });
    }

    public function syncDiscordChannels(Operation $operation, array $deletedDiscordChannelIds = []): array
    {
        if ($operation->isDraft()) {
            throw ValidationException::withMessages([
                'operation' => 'Publish the operation before syncing Discord channels.',
            ]);
        }

        $operation->load([
            'participants.user',
            'participants.discordChannel',
            'discordChannels',
        ]);

        $deletedDiscordChannelIds = collect($deletedDiscordChannelIds)
            ->filter(fn ($id) => is_string($id) && trim($id) !== '')
            ->values()
            ->all();

        if ($operation->discordChannels->isEmpty() && $deletedDiscordChannelIds === []) {
            throw ValidationException::withMessages([
                'channels' => 'Add at least one operation Discord channel first.',
            ]);
        }

        $channelPayloads = $operation->discordChannels
            ->map(function (OperationDiscordChannel $channel) use ($operation) {
                $assignedDiscordIds = $operation->participants
                    ->filter(function (OperationParticipant $participant) use ($channel) {
                        return (int) $participant->operation_discord_channel_id === (int) $channel->id
                            && $participant->synced_in_at !== null
                            && ! $participant->isSignedOffBeforeStart()
                            && filled($participant->user?->discord_id);
                    })
                    ->pluck('user.discord_id')
                    ->filter()
                    ->map(fn ($discordId) => (string) $discordId)
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'operation_channel_id' => $channel->id,
                    'name' => $channel->name,
                    'discord_channel_id' => $channel->discord_channel_id,
                    'member_discord_ids' => $assignedDiscordIds,
                ];
            })
            ->values()
            ->all();

        $result = $this->discord->syncOperationChannels($operation, $channelPayloads, $deletedDiscordChannelIds);

        if (! $result['ok']) {
            throw ValidationException::withMessages([
                'discord' => $result['message'],
            ]);
        }

        $returnedChannels = collect($result['data']['channels'] ?? [])
            ->filter(fn ($channel) => is_array($channel))
            ->keyBy(fn ($channel) => (int) ($channel['operation_channel_id'] ?? 0));

        foreach ($operation->discordChannels as $channel) {
            $returnedChannel = $returnedChannels->get((int) $channel->id);

            if (! $returnedChannel) {
                continue;
            }

            $discordChannelId = trim((string) ($returnedChannel['discord_channel_id'] ?? ''));

            if ($discordChannelId !== '') {
                $channel->forceFill([
                    'discord_channel_id' => $discordChannelId,
                ])->save();
            }
        }

        return [
            'message' => $result['message'],
            'created_channel_count' => (int) ($result['data']['created_channel_count'] ?? 0),
            'renamed_channel_count' => (int) ($result['data']['renamed_channel_count'] ?? 0),
            'deleted_channel_count' => (int) ($result['data']['deleted_channel_count'] ?? 0),
            'moved_member_count' => (int) ($result['data']['moved_member_count'] ?? 0),
            'missing_guild_member_ids' => $result['data']['missing_guild_member_ids'] ?? [],
            'runtime' => $this->build($operation->fresh([
                'participants.user',
                'participants.role',
                'participants.discordChannel',
                'discordChannels',
                'syncRuns.syncedBy:id,name,rsi_handle,discord_name',
            ])),
        ];
    }

    public function syncLayoutAndDiscordChannels(
        Operation $operation,
        array $channels,
        array $participantAssignments = []
    ): array {
        return DB::transaction(function () use ($operation, $channels, $participantAssignments) {
            $operation->loadMissing('discordChannels');

            $keptChannelIds = collect($channels)
                ->pluck('id')
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $deletedDiscordChannelIds = $operation->discordChannels
                ->filter(fn ($channel) => ! in_array((int) $channel->id, $keptChannelIds, true))
                ->pluck('discord_channel_id')
                ->filter(fn ($id) => is_string($id) && trim($id) !== '')
                ->values()
                ->all();

            $this->saveLayout($operation, $channels, $participantAssignments);

            return $this->syncDiscordChannels($operation->fresh(), $deletedDiscordChannelIds);
        });
    }

    public function cleanupDiscordChannels(Operation $operation): array
    {
        $operation->loadMissing('discordChannels');

        if ($operation->discordChannels->isEmpty()) {
            return [
                'ok' => true,
                'message' => null,
                'deleted_channel_count' => 0,
                'removed_layout_count' => 0,
            ];
        }

        $discordChannelIds = $operation->discordChannels
            ->pluck('discord_channel_id')
            ->filter(fn ($id) => is_string($id) && trim($id) !== '')
            ->values()
            ->all();

        $deletedChannelCount = 0;

        if ($discordChannelIds !== []) {
            $result = $this->discord->syncOperationChannels($operation, [], $discordChannelIds);

            if (! $result['ok']) {
                return [
                    'ok' => false,
                    'message' => $result['message'],
                    'deleted_channel_count' => 0,
                    'removed_layout_count' => 0,
                ];
            }

            $deletedChannelCount = (int) ($result['data']['deleted_channel_count'] ?? 0);
        }

        $removedLayoutCount = DB::transaction(function () use ($operation) {
            $channelIds = $operation->discordChannels
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ($channelIds !== []) {
                $operation->participants()
                    ->whereIn('operation_discord_channel_id', $channelIds)
                    ->update(['operation_discord_channel_id' => null]);
            }

            $removedCount = $operation->discordChannels()->count();
            $operation->discordChannels()->delete();

            return $removedCount;
        });

        return [
            'ok' => true,
            'message' => null,
            'deleted_channel_count' => $deletedChannelCount,
            'removed_layout_count' => $removedLayoutCount,
        ];
    }

    protected function syncRunPayload($syncRun): ?array
    {
        if (! $syncRun) {
            return null;
        }

        return [
            'id' => $syncRun->id,
            'synced_at' => $syncRun->synced_at?->toIso8601String(),
            'source_channel_ids' => $syncRun->source_channel_ids ?? [],
            'present_user_ids' => $syncRun->present_user_ids ?? [],
            'no_show_user_ids' => $syncRun->no_show_user_ids ?? [],
            'walk_in_user_ids' => $syncRun->walk_in_user_ids ?? [],
            'synced_by' => $syncRun->syncedBy ? [
                'id' => $syncRun->syncedBy->id,
                'name' => $syncRun->syncedBy->rsi_handle
                    ?? $syncRun->syncedBy->discord_name
                    ?? $syncRun->syncedBy->name,
            ] : null,
        ];
    }

    protected function participantPayload(OperationParticipant $participant): array
    {
        return [
            'id' => $participant->id,
            'attendance_status' => $participant->attendance_status,
            'runtime_status' => $participant->runtime_status,
            'runtime_source' => $participant->runtime_source,
            'signed_off_at' => $participant->signed_off_at?->toIso8601String(),
            'synced_in_at' => $participant->synced_in_at?->toIso8601String(),
            'notes' => $participant->notes,
            'runtime_notes' => $participant->runtime_notes,
            'slot' => $participant->slot,
            'operation_discord_channel_id' => $participant->operation_discord_channel_id,
            'operation_discord_channel_key' => $participant->operation_discord_channel_id
                ? 'saved-' . $participant->operation_discord_channel_id
                : null,
            'role' => $participant->role ? [
                'id' => $participant->role->id,
                'role_name' => $participant->role->role_name,
                'role_display_name' => $participant->role->role_display_name,
            ] : null,
            'user' => [
                'id' => $participant->user?->id,
                'rsi_handle' => $participant->user?->rsi_handle,
                'display_name' => $participant->user?->display_name,
                'name' => $participant->user?->name,
                'discord_id' => $participant->user?->discord_id,
                'discord_avatar' => $participant->user?->discord_avatar,
                'avatar' => $participant->user?->avatar,
            ],
        ];
    }

    protected function discordChannelPayload(OperationDiscordChannel $channel): array
    {
        return [
            'id' => $channel->id,
            'client_key' => 'saved-' . $channel->id,
            'name' => $channel->name,
            'discord_channel_id' => $channel->discord_channel_id,
            'sort_order' => $channel->sort_order,
            'assigned_participant_ids' => $channel->participants()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
            ->all(),
        ];
    }

    protected function ensureRuntimeAvailable(Operation $operation): void
    {
        if ($operation->isDraft()) {
            throw ValidationException::withMessages([
                'operation' => 'Publish the operation before using the run tool.',
            ]);
        }

        if ($operation->isCompleted() || $operation->isCanceled()) {
            throw ValidationException::withMessages([
                'operation' => 'The run tool is only available for active operations.',
            ]);
        }
    }

    protected function ensureParticipantBelongsToOperation(Operation $operation, OperationParticipant $participant): void
    {
        if ((int) $participant->operation_id !== (int) $operation->id) {
            throw ValidationException::withMessages([
                'participant' => 'That roster row does not belong to this operation.',
            ]);
        }
    }

    protected function participantStatusUpdates(OperationParticipant $participant, string $status): array
    {
        return match ($status) {
            'waiting' => [
                'attendance_status' => 'signed_up',
                'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
                'runtime_source' => $participant->runtime_source ?: OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
                'signed_off_at' => null,
                'synced_in_at' => null,
            ],
            'present' => [
                'attendance_status' => 'signed_up',
                'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
                'runtime_source' => $participant->runtime_source ?: OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
                'signed_off_at' => null,
                'synced_in_at' => $participant->synced_in_at ?: now(),
            ],
            OperationParticipant::RUNTIME_STATUS_OPERATION_FINISHED => [
                'attendance_status' => 'attended',
                'runtime_status' => OperationParticipant::RUNTIME_STATUS_OPERATION_FINISHED,
                'runtime_source' => $participant->runtime_source ?: OperationParticipant::RUNTIME_SOURCE_MANUAL,
                'signed_off_at' => null,
                'synced_in_at' => $participant->synced_in_at ?: now(),
            ],
            OperationParticipant::RUNTIME_STATUS_NO_SHOW => [
                'attendance_status' => 'missed',
                'runtime_status' => OperationParticipant::RUNTIME_STATUS_NO_SHOW,
                'runtime_source' => $participant->runtime_source ?: OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
                'signed_off_at' => null,
                'synced_in_at' => null,
            ],
            OperationParticipant::RUNTIME_STATUS_EXCUSED => [
                'attendance_status' => 'missed',
                'runtime_status' => OperationParticipant::RUNTIME_STATUS_EXCUSED,
                'runtime_source' => $participant->runtime_source ?: OperationParticipant::RUNTIME_SOURCE_MANUAL,
                'signed_off_at' => null,
                'synced_in_at' => null,
            ],
            OperationParticipant::RUNTIME_STATUS_SIGNED_OFF_BEFORE_START => [
                'attendance_status' => 'signed_up',
                'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_OFF_BEFORE_START,
                'runtime_source' => $participant->runtime_source ?: OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
                'signed_off_at' => $participant->signed_off_at ?: now(),
                'synced_in_at' => null,
            ],
            OperationParticipant::RUNTIME_STATUS_TECHNICAL_ISSUE => [
                'attendance_status' => 'missed',
                'runtime_status' => OperationParticipant::RUNTIME_STATUS_TECHNICAL_ISSUE,
                'runtime_source' => $participant->runtime_source ?: OperationParticipant::RUNTIME_SOURCE_MANUAL,
                'signed_off_at' => null,
                'synced_in_at' => $participant->synced_in_at,
            ],
            default => throw ValidationException::withMessages([
                'status' => 'Pick a valid runtime status.',
            ]),
        };
    }

    protected function shouldPreserveSyncedRuntimeStatus(OperationParticipant $participant): bool
    {
        return in_array($participant->runtime_status, [
            OperationParticipant::RUNTIME_STATUS_OPERATION_FINISHED,
            OperationParticipant::RUNTIME_STATUS_TECHNICAL_ISSUE,
        ], true);
    }

    protected function shouldPreserveAbsentRuntimeStatus(OperationParticipant $participant): bool
    {
        if ($this->shouldPreserveSyncedRuntimeStatus($participant)) {
            return true;
        }

        if ($participant->runtime_status === OperationParticipant::RUNTIME_STATUS_EXCUSED) {
            return true;
        }

        return $participant->runtime_source === OperationParticipant::RUNTIME_SOURCE_WALK_IN
            && $participant->synced_in_at !== null
            && $participant->runtime_status !== OperationParticipant::RUNTIME_STATUS_NO_SHOW;
    }

    protected function recordManualWalkInOnLatestSync(Operation $operation, User $actor, User $user): void
    {
        $latestSyncRun = $operation->syncRuns()->first();

        if (! $latestSyncRun) {
            $operation->syncRuns()->create([
                'synced_by_user_id' => $actor->id,
                'synced_at' => now(),
                'source_channel_ids' => [],
                'present_user_ids' => [$user->id],
                'no_show_user_ids' => [],
                'walk_in_user_ids' => [$user->id],
            ]);

            return;
        }

        $presentUserIds = collect($latestSyncRun->present_user_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->push((int) $user->id)
            ->unique()
            ->values()
            ->all();

        $walkInUserIds = collect($latestSyncRun->walk_in_user_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->push((int) $user->id)
            ->unique()
            ->values()
            ->all();

        $noShowUserIds = collect($latestSyncRun->no_show_user_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === (int) $user->id)
            ->values()
            ->all();

        $latestSyncRun->forceFill([
            'synced_by_user_id' => $actor->id,
            'synced_at' => now(),
            'present_user_ids' => $presentUserIds,
            'no_show_user_ids' => $noShowUserIds,
            'walk_in_user_ids' => $walkInUserIds,
        ])->save();
    }
}
