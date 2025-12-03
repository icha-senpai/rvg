<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;




Route::prefix('v1')->group(function () {
    require base_path('routes/api_v1.php');
    require __DIR__.'/bot.php';
});

// When V2 is ready:
// Route::prefix('v2')->group(function () {
//     require base_path('routes/api_v2.php');
// });
