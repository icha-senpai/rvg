<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\AccessControl\AccessService;

class AccessControlServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(AccessService::class, function ($app) {
            return new AccessService();
        });
    }
}
