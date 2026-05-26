<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

// WEB CONTROLLERS
use App\Http\Controllers\Web\OperationPageController;
use App\Http\Controllers\Web\OperationCalendarController;
use App\Http\Controllers\Web\OperationParticipantController;
use App\Http\Controllers\Web\OperationTransitionController;
use App\Http\Controllers\Web\ArchiveController;
use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\SquadronLeaderController;
use App\Http\Controllers\Web\SquadronPromotionController;
use App\Http\Controllers\Web\SquadronManageController;
use App\Http\Controllers\Web\SquadronPageController;
use App\Http\Controllers\Web\MediaController;
use App\Http\Controllers\Web\MemberDirectoryController;
use App\Http\Controllers\Web\DiscordAuthController;
use App\Http\Controllers\Web\VerifyController;
use App\Http\Requests\UpdateMeRequest;
use App\Http\Resources\MeResource;
use App\Models\Squadron;
use App\Models\User;

// ADMIN SUBCONTROLLERS
use App\Http\Controllers\Admin\SquadronRankController;
use App\Http\Controllers\Web\Admin\AdminUserController;
use App\Http\Controllers\Web\Admin\AdminSquadronController;
use App\Http\Controllers\Web\Admin\AdminRoleController;

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

Route::get('/verify', [VerifyController::class, 'show'])->name('verify');

Route::get('/login', function () {
    if (Auth::check()) {
        return redirect()->to('/');
    }

    return redirect()->to('/auth/discord');
})->name('login');

Route::get('/user/{user}', function (User $user) {
    if ($user->rsi_handle) {
        return redirect()->route('member.profile', ['user' => $user->rsi_handle]);
    }

    return Inertia::render('Member/userpage', [
        'profileUser' => (new MeResource($user->load('roles')))->resolve(request()),
    ]);
})
    ->whereNumber('user')
    ->middleware(['auth', 'rsi.verified']);

Route::get('/user/{user:rsi_handle}', function (User $user) {
    return Inertia::render('Member/userpage', [
        'profileUser' => (new MeResource($user->load('roles')))->resolve(request()),
    ]);
})
    ->middleware(['auth', 'rsi.verified'])
    ->name('member.profile');

Route::get('/members', [MemberDirectoryController::class, 'index'])
    ->middleware(['auth', 'rsi.verified'])
    ->name('members.index');

Route::get('/me', function () {
    $user = Auth::user();
    if (! $user) {
        return redirect()->route('home');
    }

    if ($user->rsi_handle) {
        return redirect()->route('member.profile', ['user' => $user->rsi_handle]);
    }

    return redirect()->to('/user/' . $user->id);
})
    ->middleware(['auth', 'rsi.verified']);

Route::put('/me', function (UpdateMeRequest $request) {
    $user = $request->user();

    $allowed = [
        'bio',
        'timezone',
        'favorite_ships',
        'favorite_guns',
        'primary_role',
        'secondary_role',
        'experience_ratings',
        'preferred_gameplay_style',
        'callsign',
        'typical_op_commitment',
        'preferred_roles',
        'notification_settings',
        'availability_status',
        'loa_note',
        'personal_tags',
    ];

    $safeData = collect($request->validated())
        ->only($allowed)
        ->toArray();

    $user->fill($safeData);
    $user->save();

    return back()->with('success', 'Profile updated.');
})
    ->middleware(['auth', 'rsi.verified'])
    ->name('me.update');

/*
|--------------------------------------------------------------------------
| DISCORD OAUTH (MUST BE WEB ROUTES — NO API PREFIX)
|--------------------------------------------------------------------------
*/
Route::get('/auth/discord', [DiscordAuthController::class, 'redirect'])
    ->name('discord.redirect');

Route::get('/auth/discord/callback', [DiscordAuthController::class, 'callback'])
    ->name('discord.callback');

Route::middleware(['auth'])->group(function () {
    Route::post('/verify/code', [VerifyController::class, 'generateCode'])
        ->name('verify.code');

    Route::post('/verify/rsi', [VerifyController::class, 'verifyRsi'])
        ->name('verify.rsi');
});


/*
|--------------------------------------------------------------------------
| OPERATION PAGE ROUTES (PUBLIC VIEW + AUTHED CREATE/EDIT)
|--------------------------------------------------------------------------
*/

// Public listings & viewing
Route::get('/operations/dashboard', [OperationPageController::class, 'index'])
    ->middleware(['auth', 'rsi.verified'])
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

    Route::post('/operation-templates', [OperationPageController::class, 'storeTemplate'])
        ->name('operations.templates.store');

    Route::put('/operation-templates/{template}', [OperationPageController::class, 'updateTemplate'])
        ->name('operations.templates.update');

    Route::delete('/operation-templates/{template}', [OperationPageController::class, 'destroyTemplate'])
        ->name('operations.templates.destroy');

    Route::post('/squadrons/{squadron}/operations', [OperationPageController::class, 'store'])
        ->name('operations.store');
        
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
| ARCHIVE ROUTES (Authenticated verified members)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'rsi.verified'])
    ->prefix('archive')
    ->name('archive.')
    ->group(function () {
        Route::get('/', [ArchiveController::class, 'index'])->name('index');
        Route::get('/{topic:slug}', [ArchiveController::class, 'topic'])->name('topic');
        Route::get('/{topic:slug}/{entry:slug}', [ArchiveController::class, 'entry'])->name('entry');
    });


/*
|--------------------------------------------------------------------------
| SQUADRON PROMOTIONS (Leader functions)
|--------------------------------------------------------------------------
*/
Route::post('/squadrons/{squadron}/leader', 
    [SquadronLeaderController::class, 'store']
)->middleware(['auth', 'rsi.verified'])->name('squadrons.assignLeader');

Route::post('/squadrons/{squadron}/promote-lieutenant', 
    [SquadronPromotionController::class, 'promoteLieutenant']
)->middleware(['auth', 'rsi.verified'])->name('squadrons.promoteLieutenant');


Route::get('/squadrons/{squadron}', [SquadronPageController::class, 'showById'])
    ->whereNumber('squadron')
    ->middleware(['auth', 'rsi.verified']);

Route::get('/squadrons/{squadron:slug}', [SquadronPageController::class, 'show'])
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

    Route::post('/squadrons/{squadron}/join',
        [SquadronManageController::class, 'join']
    )->name('squadrons.join');

    Route::post('/squadrons/{squadron}/leave',
        [SquadronManageController::class, 'leave']
    )->name('squadrons.leave');

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

    Route::put('/squadrons/{squadron}/emblem',
        [SquadronManageController::class, 'selectEmblem']
    )->name('squadrons.emblem.select');

    Route::post('/squadrons/{squadron}/demote-lieutenant',
        [SquadronManageController::class, 'demoteLieutenant']
    )->name('squadrons.demoteLieutenant');
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

    Route::get('/{media}/download', [MediaController::class, 'download'])
        ->whereNumber('media')
        ->name('media.download');

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

        Route::get('/media/{media}/download', [MediaController::class, 'download'])
            ->whereNumber('media')
            ->name('admin.media.download');

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


        Route::post('/users/update', [AdminUserController::class, 'update'])
            ->name('admin.users.update');

        Route::post('/users/update-roles', [AdminUserController::class, 'updateRoles'])
            ->name('admin.users.updateRoles');

        Route::post('/users/unverify', [AdminUserController::class, 'unverify'])
            ->name('admin.users.unverify');

        /*
        |-----------------------
        | SQUADRON MANAGEMENT
        |-----------------------
        */
        Route::get('/squadrons', [AdminSquadronController::class, 'index'])
            ->name('admin.squadrons.index')
            ->middleware('can:access-admin-panel');

        Route::post('/squadrons/store', [AdminSquadronController::class, 'store'])
            ->name('admin.squadrons.store');

        Route::post('/squadrons/update', [AdminSquadronController::class, 'update'])
            ->name('admin.squadrons.update');

        Route::post('/squadrons/delete', [AdminSquadronController::class, 'destroy'])
            ->name('admin.squadrons.delete');

        Route::post('/squadrons/members/add', [AdminSquadronController::class, 'addMember'])
            ->name('admin.squadrons.members.add');

        Route::post('/squadrons/members/update', [AdminSquadronController::class, 'updateMember'])
            ->name('admin.squadrons.members.update');

        Route::post('/squadrons/members/remove', [AdminSquadronController::class, 'removeMember'])
            ->name('admin.squadrons.members.remove');

        Route::post('/roles/store', [AdminRoleController::class, 'store'])
            ->name('admin.roles.store');

        Route::post('/roles/update', [AdminRoleController::class, 'update'])
            ->name('admin.roles.update');

        Route::post('/roles/delete', [AdminRoleController::class, 'destroy'])
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

Route::fallback(function () {
    return Inertia::render('Error', [
        'status' => 404,
    ])->toResponse(request())->setStatusCode(404);
})->withoutMiddleware([
    \App\Http\Middleware\ForceDiscordAuth::class,
    \App\Http\Middleware\EnforceMaxAuthAge::class,
]);
