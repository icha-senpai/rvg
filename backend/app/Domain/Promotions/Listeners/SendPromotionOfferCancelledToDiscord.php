<?php

namespace App\Domain\Promotions\Listeners;

use App\Domain\Promotions\Events\PromotionOfferCancelled;
use App\Domain\Promotions\PromotionWorkflowService;
use App\Models\PromotionOffer;
use RuntimeException;
use Illuminate\Support\Facades\Http;

class SendPromotionOfferCancelledToDiscord
{
    public function __construct(
        protected PromotionWorkflowService $workflow,
    ) {}

    public function handle(PromotionOfferCancelled $event): void
    {
        $offer = $event->offer->fresh(['member', 'promoter']) ?? $event->offer;

        $response = Http::withHeaders([
            'X-Bot-Secret' => config('services.bot.secret'),
        ])
            ->asJson()
            ->post(config('services.bot.url') . '/promotion-offers/cancel', $this->workflow->discordStatusPayload($offer, PromotionOffer::STATE_CANCELLED));

        if (! $response->successful()) {
            throw new RuntimeException('Promotion cancellation webhook failed: ' . $response->status() . ' ' . $response->body());
        }
    }
}
