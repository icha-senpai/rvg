<?php

namespace App\Domain\Operations\Listeners;

use App\Domain\Operations\Events\OperationUpdated;
use App\Domain\Operations\OperationDiscordAnnouncementService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendOperationUpdatedToDiscord
{
    public function __construct(
        protected OperationDiscordAnnouncementService $discordAnnouncements,
    ) {
    }

    public function handle(OperationUpdated $event)
    {
        $op = $event->operation->fresh(['squadron', 'creator']);

        Log::info("📡 Listener fired for UPDATED operation {$op->id}");

        try {
            $deletePayload = $this->discordAnnouncements->deletePayload($op);

            if ($deletePayload !== null) {
                $deleteResponse = Http::withHeaders([
                    'X-Bot-Secret' => config('services.bot.secret'),
                ])
                ->asJson()
                ->post(config('services.bot.url') . '/op-delete', $deletePayload);

                if (! in_array($deleteResponse->status(), [200, 204, 404], true)) {
                    Log::warning('❌ Bot delete webhook failed; skipping repost to avoid duplicates', [
                        'operation_id' => $op->id,
                        'status' => $deleteResponse->status(),
                        'body' => $deleteResponse->body(),
                    ]);

                    return;
                }

                $this->discordAnnouncements->clearAnnouncementTracking($op);
            }

            $response = Http::withHeaders([
                'X-Bot-Secret' => config('services.bot.secret'),
            ])
            ->asJson()
            ->post(config('services.bot.url') . '/op-updated', $this->discordAnnouncements->buildAnnouncementPayload($op));

            if ($response->successful()) {
                $this->discordAnnouncements->persistAnnouncementResponse($op, $response->json());
            }

            Log::info("🌐 Bot update webhook delivered. Status: {$response->status()}");
        } catch (\Throwable $e) {
            Log::warning("❌ Bot update webhook failed: {$e->getMessage()}", [
                'operation_id' => $op->id,
            ]);
        }
    }
}
