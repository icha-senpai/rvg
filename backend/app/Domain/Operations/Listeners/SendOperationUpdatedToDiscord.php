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
                    'ping' => false,
                ];

                if ($op->squadron_name) {
                    $payload['squadron_name'] = $op->squadron_name;
                }

                return $payload;
            })());

            Log::info("🌐 Bot update webhook delivered. Status: {$response->status()}");
        } catch (\Throwable $e) {
            Log::warning("❌ Bot update webhook failed: {$e->getMessage()}", [
                'operation_id' => $op->id,
            ]);
        }
    }
}
