<?php

namespace App\Domain\Promotions\Listeners;

use App\Domain\Promotions\Events\PromotionOfferCreated;
use App\Domain\Promotions\PromotionWorkflowService;
use RuntimeException;
use Illuminate\Support\Facades\Http;

class SendPromotionOfferCreatedToDiscord
{
    public function __construct(
        protected PromotionWorkflowService $workflow,
    ) {}

    public function handle(PromotionOfferCreated $event): void
    {
        $offer = $event->offer->fresh(['member.roles:id,slug,name', 'promoter']) ?? $event->offer;

        $response = Http::withHeaders([
            'X-Bot-Secret' => config('services.bot.secret'),
        ])
            ->asJson()
            ->post(config('services.bot.url') . '/promotion-offers/create', $this->workflow->discordCreatePayload($offer));

        if (! $response->successful()) {
            throw new RuntimeException('Promotion offer webhook failed: ' . $response->status() . ' ' . $response->body());
        }

        $offer->forceFill([
            'discord_dm_message_id' => $response->json('dm_message_id'),
            'discord_quarter_message_id' => $response->json('quarter_message_id'),
        ])->save();
    }
}
