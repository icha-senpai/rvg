<?php

namespace App\Domain\Operations\Listeners;

use App\Domain\Operations\Events\OperationPublished;
use App\Domain\Operations\OperationDiscordAnnouncementService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendOperationPublishedToDiscord
{
    public function __construct(
        protected OperationDiscordAnnouncementService $discordAnnouncements,
    ) {
    }

    public function handle(OperationPublished $event)
    {
        $op = $event->operation->fresh(['squadron', 'creator']);

        Log::info("📡 Listener fired for operation {$op->id}");
        Log::info("Sending timestamp to bot", [
            'starts_at' => $op->starts_at,
            'unix' => $op->starts_at?->timestamp,
        ]);

        try {
            $payload = $this->discordAnnouncements->buildAnnouncementPayload($op);

            $response = Http::withHeaders([
                'X-Bot-Secret' => config('services.bot.secret'),
            ])
            ->asJson()
            ->post(config('services.bot.url') . '/op-published', $payload);

            if ($response->successful()) {
                $this->discordAnnouncements->persistAnnouncementResponse($op, $response->json());
            }

            Log::info("🌐 Bot webhook delivered. Status: {$response->status()}");
        } catch (\Throwable $e) {
            Log::warning("❌ Bot webhook failed: {$e->getMessage()}", [
                'operation_id' => $op->id,
            ]);
        }
    }
}
