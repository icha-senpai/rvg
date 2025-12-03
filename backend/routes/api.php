<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;




Route::prefix('V1')->group(function () {
    require base_path('routes/Api_V1.php');
    require __DIR__.'/bot.php';
});

// When V2 is ready:
// Route::prefix('v2')->group(function () {
//     require base_path('routes/api_v2.php');
// });
