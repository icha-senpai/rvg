<?php

namespace App\Domain\Operations\Listeners;

use App\Domain\Operations\Events\OperationPublished;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendOperationPublishedToDiscord
{
    public function handle(OperationPublished $event)
    {
        $op = $event->operation->fresh(['squadron']);

        Log::info("📡 Listener fired for operation {$op->id}");
        Log::info("Sending timestamp to bot", [
            'starts_at' => $op->starts_at,
            'unix' => $op->starts_at?->timestamp,
        ]);

        try {
            $response = Http::withHeaders([
                'X-Bot-Secret' => config('services.bot.secret'),
            ])
            ->asJson()   // <<< 🔥 REQUIRED: ensures JSON payload is correct
            ->post(config('services.bot.url') . '/op-published', [
                'id' => $op->id,
                'title' => $op->title,
                'description' => $op->description,
                'starts_at_discord' => $op->starts_at ? "<t:{$op->starts_at->timestamp}:f>" : null,
                'operation_strictness' => $op->operation_strictness,
                'visibility' => $op->visibility,
                'squadron_name' => $op->squadron?->name,
            ]);

            Log::info("🌐 Bot webhook delivered. Status: {$response->status()}");
        } catch (\Throwable $e) {
            Log::warning("❌ Bot webhook failed: {$e->getMessage()}", [
                'operation_id' => $op->id,
            ]);
        }
    }
}
