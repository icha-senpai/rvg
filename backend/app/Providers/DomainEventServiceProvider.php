<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class DomainEventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Domain\Operations\Events\OperationPublished::class => [
            \App\Domain\Operations\Listeners\SendOperationPublishedToDiscord::class,
        ],
        \App\Domain\Operations\Events\OperationCompleted::class => [
            \App\Domain\Operations\Listeners\UpdateOperationCompletionStats::class,
        ],
        \App\Domain\Operations\Events\OperationCanceled::class => [
            \App\Domain\Operations\Listeners\UpdateOperationCancellationStats::class,
        ],
        \App\Domain\Operations\Events\OperationUpdated::class => [
            \App\Domain\Operations\Listeners\SendOperationUpdatedToDiscord::class,
        ],
        \App\Domain\AccessControl\Events\RoleAssigned::class => [
            \App\Domain\AccessControl\Listeners\FlushUserAccessCache::class,
        ],
        \App\Domain\AccessControl\Events\RoleRevoked::class => [
            \App\Domain\AccessControl\Listeners\FlushUserAccessCache::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false; // keep manual registration clean
    }
}
