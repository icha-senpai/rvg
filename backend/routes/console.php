<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Domain\Promotions\PromotionWorkflowService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('promotions:expire-pending', function (PromotionWorkflowService $workflow) {
    $count = $workflow->expireDueOffers();

    $this->info("Expired {$count} promotion offer(s).");
})->purpose('Expire stale pending promotion offers.');

Schedule::command('promotions:expire-pending')->everyFiveMinutes();
