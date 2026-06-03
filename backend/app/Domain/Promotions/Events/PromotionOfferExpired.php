<?php

namespace App\Domain\Promotions\Events;

use App\Models\PromotionOffer;

class PromotionOfferExpired
{
    public function __construct(
        public PromotionOffer $offer,
    ) {}
}
