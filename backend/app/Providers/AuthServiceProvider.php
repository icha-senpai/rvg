<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

use App\Models\User;
use App\Models\Squadron;
use App\Models\Operation;
use App\Models\RsiChangeRequest;
use App\Models\Media;

use App\Policies\SquadronPolicy;
use App\Policies\OperationPolicy;
use App\Policies\RsiChangeRequestPolicy;
use App\Policies\AdminPolicy;
use App\Policies\MediaPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Squadron::class        => SquadronPolicy::class,
        Operation::class       => OperationPolicy::class,
        RsiChangeRequest::class => RsiChangeRequestPolicy::class,
        User::class            => AdminPolicy::class,
        Media::class           => MediaPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Admin panel access gate
        Gate::define('access-admin-panel', function (User $user) {
            return $user->hasRole('director') 
                || $user->hasRole('tech_director');
        });
    }
}
