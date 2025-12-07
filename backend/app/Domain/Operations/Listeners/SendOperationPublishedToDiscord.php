<?php

namespace App\Domain\Operations\Listeners;

use App\Domain\Operations\Events\OperationPublished;
use Illuminate\Support\Facades\Http;

class SendOperationPublishedToDiscord
{
    public function handle(OperationPublished $event)
    {
        $op = $event->operation;

        \Log::info("📡 Listener fired for operation {$op->id}");

        try {
            $response = Http::withHeaders([
                'X-Bot-Secret' => config('services.bot.secret'),
            ])->post(config('services.bot.url') . '/op-published', [
                'id' => $op->id,
                'title' => $op->title,
                'description' => $op->description,
                'starts_at' => optional($op->starts_at)->clone()->timezone('UTC')->format('Y-m-d\TH:i:s\Z'),
                'operation_strictness' => $op->operation_strictness,
                'visibility' => $op->visibility,
                'squadron_name' => $op->squadron->name ?? null,
            ]);

            \Log::info("🌐 Bot webhook delivered. Status: {$response->status()}");
        } catch (\Throwable $e) {
            \Log::warning("❌ Bot webhook failed: {$e->getMessage()}", [
                'operation_id' => $op->id,
            ]);
        }
    }
}
