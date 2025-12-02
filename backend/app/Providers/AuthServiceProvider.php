<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

use App\Models\Squadron;
use App\Models\Operation;
use App\Models\RsiChangeRequest;
use App\Policies\SquadronPolicy;
use App\Policies\OperationPolicy;
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
        Operation::class => OperationPolicy::class,
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
