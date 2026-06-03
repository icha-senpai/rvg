<?php

namespace App\Domain\Promotions\Events;

use App\Models\PromotionOffer;

class PromotionOfferCancelled
{
    public function __construct(
        public PromotionOffer $offer,
    ) {}
}
