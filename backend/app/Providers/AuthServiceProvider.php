<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

use App\Models\Squadron;
use App\Models\Event;
use App\Models\Mission;
use App\Models\RsiChangeRequest;
use App\Policies\SquadronPolicy;
use App\Policies\EventPolicy;
use App\Policies\MissionPolicy;
use App\Policies\RsiChangeRequestPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Squadron::class => SquadronPolicy::class,
        Event::class => EventPolicy::class,
        Mission::class => MissionPolicy::class,
        RsiChangeRequest::class => RsiChangeRequestPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Laravel 12 still requires parent::boot() for policy registration
        $this->registerPolicies();

        // Extra gates (if any) can go here later
    }
}
