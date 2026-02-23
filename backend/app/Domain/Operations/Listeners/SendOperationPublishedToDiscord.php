<?php

namespace App\Domain\Operations\Listeners;

use App\Domain\Operations\Events\OperationPublished;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendOperationPublishedToDiscord
{
    public function handle(OperationPublished $event)
    {
        $op = $event->operation->fresh(['squadron', 'creator']);

        Log::info("📡 Listener fired for operation {$op->id}");
        Log::info("Sending timestamp to bot", [
            'starts_at' => $op->starts_at,
            'unix' => $op->starts_at?->timestamp,
        ]);

        try {
            $operationLeader = $op->creator?->rsi_handle
                ?? $op->creator?->name;

            $operationLeaderDiscordId = $op->creator?->discord_id;
            $operationLeaderDiscordName = $op->creator?->discord_name;
            $operationLeaderDiscordAvatar = $op->creator?->discord_avatar;

            $payload = [
                'id' => $op->id,
                'title' => $op->title,
                'description' => $op->description,
                'starts_at_discord' => $op->starts_at ? "<t:{$op->starts_at->timestamp}:f>" : null,
                'operation_type' => $op->operation_type,
                'operation_strictness' => $op->operation_strictness,
                'start_location' => $op->start_location,
                'operation_leader' => $operationLeader,
                'operation_leader_discord_id' => $operationLeaderDiscordId,
                'operation_leader_discord_name' => $operationLeaderDiscordName,
                'operation_leader_discord_avatar' => $operationLeaderDiscordAvatar,
            ];

            if ($op->squadron_name) {
                $payload['squadron_name'] = $op->squadron_name;
            }

            $response = Http::withHeaders([
                'X-Bot-Secret' => config('services.bot.secret'),
            ])
            ->asJson()   // <<< 🔥 REQUIRED: ensures JSON payload is correct
            ->post(config('services.bot.url') . '/op-published', $payload);

            // Persist the Discord message id so we can delete + repost on future updates.
            // We do not edit embeds in-place; the website is the source of truth.
            $messageId = $response->json('message_id');
            if (is_string($messageId) && $messageId !== '') {
                $op->forceFill([
                    'discord_message_id' => $messageId,
                ])->saveQuietly();
            }

            Log::info("🌐 Bot webhook delivered. Status: {$response->status()}");
        } catch (\Throwable $e) {
            Log::warning("❌ Bot webhook failed: {$e->getMessage()}", [
                'operation_id' => $op->id,
            ]);
        }
    }
}
