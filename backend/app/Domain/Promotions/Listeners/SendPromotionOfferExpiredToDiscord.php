<?php

namespace App\Domain\Promotions\Listeners;

use App\Domain\Promotions\Events\PromotionOfferExpired;
use App\Domain\Promotions\PromotionWorkflowService;
use App\Models\PromotionOffer;
use RuntimeException;
use Illuminate\Support\Facades\Http;

class SendPromotionOfferExpiredToDiscord
{
    public function __construct(
        protected PromotionWorkflowService $workflow,
    ) {}

    public function handle(PromotionOfferExpired $event): void
    {
        $offer = $event->offer->fresh(['member', 'promoter']) ?? $event->offer;

        $response = Http::withHeaders([
            'X-Bot-Secret' => config('services.bot.secret'),
        ])
            ->asJson()
            ->post(config('services.bot.url') . '/promotion-offers/expire', $this->workflow->discordStatusPayload($offer, PromotionOffer::STATE_EXPIRED));

        if (! $response->successful()) {
            throw new RuntimeException('Promotion expiry webhook failed: ' . $response->status() . ' ' . $response->body());
        }
    }
}
