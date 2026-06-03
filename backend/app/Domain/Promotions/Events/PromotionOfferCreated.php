<?php

namespace App\Domain\Promotions\Events;

use App\Models\PromotionOffer;

class PromotionOfferCreated
{
    public function __construct(
        public PromotionOffer $offer,
    ) {}
}
