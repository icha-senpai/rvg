<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class DomainEventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Domain\Operations\Events\OperationPublished::class => [
            \App\Domain\Operations\Listeners\SendOperationPublishedToDiscord::class,
        ],
        \App\Domain\Operations\Events\OperationUpdated::class => [
            \App\Domain\Operations\Listeners\SendOperationUpdatedToDiscord::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false; // keep manual registration clean
    }
}
