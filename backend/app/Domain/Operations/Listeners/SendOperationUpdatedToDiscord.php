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
            ->post(config('services.bot.url') . '/op-updated', [
                'id' => $op->id,
                'title' => $op->title,
                'description' => $op->description,
                'starts_at_discord' => $op->starts_at ? "<t:{$op->starts_at->timestamp}:f>" : null,
                'operation_strictness' => $op->operation_strictness,
                'visibility' => $op->visibility,
                'squadron_name' => $op->squadron_name ?: $op->squadron?->name,
            ]);

            Log::info("🌐 Bot update webhook delivered. Status: {$response->status()}");
        } catch (\Throwable $e) {
            Log::warning("❌ Bot update webhook failed: {$e->getMessage()}", [
                'operation_id' => $op->id,
            ]);
        }
    }
}
