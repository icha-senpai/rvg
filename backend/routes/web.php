<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

// WEB CONTROLLERS
use App\Http\Controllers\Web\OperationPageController;
use App\Http\Controllers\Web\OperationCalendarController;
use App\Http\Controllers\Web\OperationParticipantController;
use App\Http\Controllers\Web\OperationTransitionController;
use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\SquadronLeaderController;
use App\Http\Controllers\Web\SquadronPromotionController;
use App\Http\Controllers\Web\SquadronManageController;
use App\Http\Controllers\Web\SquadronPageController;
use App\Http\Controllers\Web\MediaController;

// AUTH CONTROLLERS
use App\Http\Controllers\Api\v1\DiscordAuthController;

// ADMIN SUBCONTROLLERS
use App\Http\Controllers\Admin\SquadronRankController;

/*
|--------------------------------------------------------------------------
| PUBLIC SPA ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (Auth::check()) {
        return Inertia::render('Welcome');
    }

    return response()->view('og-shell');
})->name('home');

Route::get('/verify', fn() => Inertia::render('Verify'))->name('verify');

Route::get('/login', function () {
    if (Auth::check()) {
        return redirect()->to('/');
    }

    return redirect()->to('/auth/discord');
})->name('login');


/*
|--------------------------------------------------------------------------
| DISCORD OAUTH (MUST BE WEB ROUTES — NO API PREFIX)
|--------------------------------------------------------------------------
*/
Route::get('/auth/discord', [DiscordAuthController::class, 'redirect'])
    ->name('discord.redirect');

Route::get('/auth/discord/callback', [DiscordAuthController::class, 'callback'])
    ->name('discord.callback');


/*
|--------------------------------------------------------------------------
| OPERATION PAGE ROUTES (PUBLIC VIEW + AUTHED CREATE/EDIT)
|--------------------------------------------------------------------------
*/

// Public listings & viewing
Route::get('/operations/dashboard', [OperationPageController::class, 'index'])
    ->name('operations.index');

Route::get('/operations', [OperationPageController::class, 'memberIndex' ])
    ->middleware(['auth', 'rsi.verified'])
    ->name('operations.member');

Route::get('/operations/member', function () {
    return redirect()->route('operations.member');
})
    ->middleware(['auth', 'rsi.verified']);

Route::get('/operations/{operation}', [OperationPageController::class, 'show'])
    ->whereNumber('operation')
    ->name('operations.show');

Route::get('/operations/{operation}/calendar', [OperationCalendarController::class, 'calendar'])
    ->whereNumber('operation')
    ->name('operations.calendar');

Route::get('/operations/{operation}/calendar.ics', [OperationCalendarController::class, 'calendar'])
    ->whereNumber('operation');



// Create operation (requires auth)
Route::middleware(['auth', 'rsi.verified'])->group(function () {
    Route::post('/operations', [OperationPageController::class, 'storeGlobal'])
        ->name('operations.storeGlobal');

    Route::post('/squadrons/{squadron}/operations', [OperationPageController::class, 'store'])
        ->name('operations.store');

    Route::get('/operations/{operation}/show-data', [OperationPageController::class, 'showData'])
        ->name('operations.showData');

    Route::get('/operations/{operation}/edit-data', [OperationPageController::class, 'editData'])
        ->name('operations.editData');
        
    Route::put('/operations/{operation}', [OperationPageController::class, 'update'])
        ->name('operations.update');
    Route::post('/operations/{operation}/publish', 
        [OperationTransitionController::class, 'publish'])
        ->name('operations.publish');
    Route::post('/operations/{operation}/start', 
        [OperationTransitionController::class, 'start'])
        ->name('operations.start');
    Route::post('/operations/{operation}/complete', 
        [OperationTransitionController::class, 'complete'])
        ->name('operations.complete');
    Route::post('/operations/{operation}/cancel', 
        [OperationTransitionController::class, 'cancel'])
        ->name('operations.cancel');
    Route::delete('/operations/{operation}', 
        [OperationPageController::class, 'destroy'])
        ->name('operations.destroy');
    Route::post('/operations/{operation}/join', [OperationParticipantController::class, 'join'])
        ->name('operations.join');

    Route::post('/operations/{operation}/leave', [OperationParticipantController::class, 'leave'])
        ->name('operations.leave');

    Route::post('/operations/{operation}/participants/{participant}/slot', [OperationParticipantController::class, 'updateSlot'])
        ->name('operations.participants.slot');

    });


/*
|--------------------------------------------------------------------------
| SQUADRON PROMOTIONS (Leader functions)
|--------------------------------------------------------------------------
*/
Route::post('/squadrons/{squadron}/leader', 
    [SquadronLeaderController::class, 'store']
)->name('squadrons.assignLeader');

Route::post('/squadrons/{squadron}/promote-lieutenant', 
    [SquadronPromotionController::class, 'promoteLieutenant']
)->name('squadrons.promoteLieutenant');


Route::get('/squadrons/{squadron}', [SquadronPageController::class, 'show'])
    ->whereNumber('squadron')
    ->middleware(['auth', 'rsi.verified'])
    ->name('squadrons.show');

/*
|--------------------------------------------------------------------------
| SQUADRON MANAGEMENT PANEL (Requires auth)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'rsi.verified'])->group(function () {

    Route::get('/squadrons', 
        [SquadronPageController::class, 'index']
    )->name('squadrons.index');

    Route::post('/squadrons/{squadron}/members/update', 
        [SquadronManageController::class, 'updateMember']
    )->name('squadrons.members.update');

    Route::post('/squadrons/{squadron}/members/remove', 
        [SquadronManageController::class, 'removeMember']
    )->name('squadrons.members.remove');

    Route::post('/squadrons/{squadron}/settings', 
        [SquadronManageController::class, 'updateSettings']
    )->name('squadrons.settings.update');

    Route::post('/squadrons/{squadron}/emblem', 
        [SquadronManageController::class, 'uploadEmblem']
    )->name('squadrons.emblem.upload');
});


/*
|--------------------------------------------------------------------------
| MEDIA ROUTES (Authenticated members)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'rsi.verified'])->prefix('media')->group(function () {

    // Upload (any authenticated user, policy enforces per-collection rules)
    Route::post('/upload', [MediaController::class, 'upload'])
        ->name('media.upload');

    // JSON list for picker modals (filtered by collection)
    Route::get('/list', [MediaController::class, 'list'])
        ->name('media.list');

    // Single media detail (JSON)
    Route::get('/{media}', [MediaController::class, 'show'])
        ->whereNumber('media')
        ->name('media.show');

    // Update metadata (alt_text)
    Route::put('/{media}', [MediaController::class, 'update'])
        ->whereNumber('media')
        ->name('media.update');

    // Delete (policy-controlled)
    Route::delete('/{media}', [MediaController::class, 'destroy'])
        ->whereNumber('media')
        ->name('media.destroy');
});


/*
|--------------------------------------------------------------------------
| ADMIN PANEL (DIRECTOR + TECH DIRECTOR ONLY)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'can:access-admin-panel'])
    ->prefix('admin')
    ->group(function () {

        // DASHBOARD
        Route::get('/dashboard', [AdminController::class, 'dashboard'])
            ->name('admin.dashboard');

        // MEDIA LIBRARY
        Route::get('/media', [MediaController::class, 'index'])
            ->name('admin.media.index');

        Route::get('/roles', function () {
            return Inertia::render('Admin/RolesIndex', [
                'roles' => \App\Models\Role::orderBy('name')->get()
            ]);
        })->name('admin.roles.index');
        /*
        |-----------------------
        | USER MANAGEMENT
        |-----------------------
        */
        Route::get('/admin/users', function () {
            return redirect()->route('admin.dashboard');
        });


        Route::post('/users/update', [AdminController::class, 'updateUser'])
            ->name('admin.users.update');

        Route::post('/users/update-roles', [AdminController::class, 'updateUserRoles'])
            ->name('admin.users.updateRoles');

        /*
        |-----------------------
        | SQUADRON MANAGEMENT
        |-----------------------
        */
        Route::get('/squadrons', [AdminController::class, 'squadronsIndex'])
            ->name('admin.squadrons.index')
            ->middleware('can:access-admin-panel');

        Route::post('/squadrons/store', [AdminController::class, 'storeSquadron'])
            ->name('admin.squadrons.store');

        Route::post('/squadrons/update', [AdminController::class, 'updateSquadron'])
            ->name('admin.squadrons.update');

        Route::post('/squadrons/delete', [AdminController::class, 'deleteSquadron'])
            ->name('admin.squadrons.delete');

        Route::post('/squadrons/members/add', [AdminController::class, 'addSquadronMember'])
            ->name('admin.squadrons.members.add');

        Route::post('/squadrons/members/update', [AdminController::class, 'updateSquadronMember'])
            ->name('admin.squadrons.members.update');

        Route::post('/squadrons/members/remove', [AdminController::class, 'removeSquadronMember'])
            ->name('admin.squadrons.members.remove');

        Route::post('/roles/store', [AdminController::class, 'storeRole'])
            ->name('admin.roles.store');

        Route::post('/roles/update', [AdminController::class, 'updateRole'])
            ->name('admin.roles.update');

        Route::post('/roles/delete', [AdminController::class, 'deleteRole'])
            ->name('admin.roles.delete');

        /*
        |-----------------------
        | RANK PROMOTIONS (Admin)
        |-----------------------
        */
        Route::post('/squadron/promote', [SquadronRankController::class, 'promote'])
            ->name('admin.squadron.promote');

        Route::post('/squadron/demote', [SquadronRankController::class, 'demote'])
            ->name('admin.squadron.demote');
    });
