<?php

namespace App\Domain\Operations\Listeners;

use App\Domain\Operations\Events\OperationUpdated;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendOperationUpdatedToDiscord
{
    public function handle(OperationUpdated $event)
    {
        $op = $event->operation->fresh(['squadron']);

        Log::info("📡 Listener fired for UPDATED operation {$op->id}");

        try {
            // We do NOT edit embeds in-place.
            // Discord is not the source of truth, so on update we delete the old message (if any)
            // and then repost a fresh embed.
            if (is_string($op->discord_message_id) && $op->discord_message_id !== '') {
                $deleteResponse = Http::withHeaders([
                    'X-Bot-Secret' => config('services.bot.secret'),
                ])
                ->asJson()
                ->post(config('services.bot.url') . '/op-delete', [
                    'message_id' => $op->discord_message_id,
                    'operation_id' => $op->id,
                ]);

                // Treat “already deleted” as success.
                if (! in_array($deleteResponse->status(), [200, 204, 404], true)) {
                    Log::warning('❌ Bot delete webhook failed; skipping repost to avoid duplicates', [
                        'operation_id' => $op->id,
                        'status' => $deleteResponse->status(),
                        'body' => $deleteResponse->body(),
                    ]);

                    return;
                }

                // Clear old id once we know the old message is gone (or already gone).
                $op->forceFill([
                    'discord_message_id' => null,
                ])->saveQuietly();
            }

            $response = Http::withHeaders([
                'X-Bot-Secret' => config('services.bot.secret'),
            ])
            ->asJson()
            ->post(config('services.bot.url') . '/op-updated', (function () use ($op) {
                $payload = [
                    'id' => $op->id,
                    'title' => $op->title,
                    'description' => $op->description,
                    'starts_at_discord' => $op->starts_at ? "<t:{$op->starts_at->timestamp}:f>" : null,
                    'operation_type' => $op->operation_type,
                    'operation_strictness' => $op->operation_strictness,
                    'visibility' => $op->visibility,
                    // Allow Discord role ping on update announcements (same behavior as publish).
                    // The bot defaults to pinging unless ping === false.
                    'ping' => true,
                ];

                if ($op->squadron_name) {
                    $payload['squadron_name'] = $op->squadron_name;
                }

                return $payload;
            })());

            // Persist the new message id so the next update can delete + repost.
            $messageId = $response->json('message_id');
            if (is_string($messageId) && $messageId !== '') {
                $op->forceFill([
                    'discord_message_id' => $messageId,
                ])->saveQuietly();
            }

            Log::info("🌐 Bot update webhook delivered. Status: {$response->status()}");
        } catch (\Throwable $e) {
            Log::warning("❌ Bot update webhook failed: {$e->getMessage()}", [
                'operation_id' => $op->id,
            ]);
        }
    }
}
