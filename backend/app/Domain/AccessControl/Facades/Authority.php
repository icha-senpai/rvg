<?php

namespace App\Domain\AccessControl\Facades;

use App\Domain\AccessControl\AccessService;
use Illuminate\Support\Facades\Facade;

class Authority extends Facade
{
    protected static function getFacadeAccessor()
    {
        return AccessService::class;
    }
}
