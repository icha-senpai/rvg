<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Api\V1\DiscordAuthController;
use App\Models\Operation;

// SPA entry points
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/verify', function () {
    return Inertia::render('Verify');
})->name('verify');

// ------------------------------------------------------------
// DISCORD OAUTH *MUST* be WEB ROUTES – NO API PREFIXES
// ------------------------------------------------------------
Route::get('/auth/discord', [DiscordAuthController::class, 'redirect'])
    ->name('discord.redirect');

Route::get('/auth/discord/callback', [DiscordAuthController::class, 'callback'])
    ->name('discord.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    
    // ---- OPERATIONS INDEX ----
    Route::get('/operations', function () {
        $operations = Operation::query()
            ->with('squadron:id,name')    // optional, remove if no relation yet
            ->orderByDesc('starts_at')
            ->get();

        return Inertia::render('Operations/MissionsIndex', [
            'operations' => $operations,
        ]);
    })->name('operations.index');


    // ---- OPERATION SHOW / LOBBY ----
    Route::get('/operations/{operation}', function (Operation $operation) {
        $operation->load([
            'participants.user:id,name,display_name',  // adjust to your schema
        ]);

        return Inertia::render('Operations/MissionShow', [
            'operation'    => $operation,
            'participants' => $operation->participants,
        ]);
    })->name('operations.show');


    // ---- OPERATION CREATE ----
    Route::get('/squadrons/{squadron}/operations/create', function ($squadron) {
        // Pass squadron_id forward so the edit form can save to the correct squadron
        return Inertia::render('Operations/MissionEditor', [
            'squadronId' => $squadron,
        ]);
    })->name('operations.create');


    // ---- OPERATION EDIT ----
    Route::get('/operations/{operation}/edit', function (Operation $operation) {
        return Inertia::render('Operations/MissionEditor', [
            'mission'     => $operation,
            'squadronId'  => $operation->squadron_id,
        ]);
    })->name('operations.edit');
});