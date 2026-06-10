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
use App\Http\Controllers\Web\SquadronLedgerController;
use App\Http\Controllers\Web\SquadronLedgerInventoryController;
use App\Http\Controllers\Web\SquadronLedgerShipAssetController;
use App\Http\Controllers\Web\SquadronLedgerTradeController;
use App\Http\Controllers\Web\SquadronLedgerTransactionController;
use App\Http\Controllers\Web\SquadronPromotionController;
use App\Http\Controllers\Web\SquadronManageController;
use App\Http\Controllers\Web\SquadronPageController;
use App\Http\Controllers\Web\MediaController;
use App\Http\Controllers\Web\MemberDirectoryController;
use App\Http\Controllers\Web\MemberDiscordRoleController;
use App\Http\Controllers\Web\MemberLedgerController;
use App\Http\Controllers\Web\MemberPromotionController;
use App\Http\Controllers\Web\MemberSettingsController;
use App\Http\Controllers\Web\DiscordAuthController;
use App\Http\Controllers\Web\LedgerInventoryController;
use App\Http\Controllers\Web\LedgerShipAssetController;
use App\Http\Controllers\Web\LedgerTradeController;
use App\Http\Controllers\Web\LedgerTransactionController;
use App\Http\Controllers\Web\OrganizationLedgerController;
use App\Http\Controllers\Web\OrganizationLedgerInventoryController;
use App\Http\Controllers\Web\OrganizationLedgerShipAssetController;
use App\Http\Controllers\Web\OrganizationLedgerTradeController;
use App\Http\Controllers\Web\OrganizationLedgerTransactionController;
use App\Http\Controllers\Web\RolePreviewController;
use App\Http\Controllers\Web\VerifyController;
use App\Http\Requests\UpdateMeRequest;
use App\Http\Resources\MeResource;
use App\Services\LedgerReferenceService;
use App\Models\Squadron;
use App\Models\User;

// ADMIN SUBCONTROLLERS
use App\Http\Controllers\Admin\SquadronRankController;
use App\Http\Controllers\Web\Admin\AdminUserController;
use App\Http\Controllers\Web\Admin\AdminSquadronController;
use App\Http\Controllers\Web\Admin\AdminRoleController;
use App\Http\Controllers\Web\Admin\AdminLedgerWipeController;
use App\Http\Controllers\Web\Admin\AdminUexDataController;
use App\Http\Controllers\Web\Admin\AdminUexSyncController;

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

Route::get('/user/{user}', function (string $user, LedgerReferenceService $ledgerReferences) {
    $profileUser = User::query()
        ->when(ctype_digit($user), fn ($query) => $query->orWhere('id', (int) $user))
        ->orWhere('rsi_handle', $user)
        ->firstOrFail();

    if (ctype_digit($user) && $profileUser->rsi_handle) {
        return redirect()->route('member.profile', ['user' => $profileUser->rsi_handle]);
    }

    return Inertia::render('Member/userpage', [
        'profileUser' => (new MeResource($profileUser->load([
            'roles:id,slug,name',
            'squadrons:id,name',
        ])))->resolve(request()),
        'favoriteShipOptions' => $ledgerReferences->shipReferenceOptions(),
        'favoriteItemOptions' => $ledgerReferences->itemReferenceOptions(),
    ]);
})
    ->middleware(['auth', 'rsi.verified'])
    ->name('member.profile');

Route::post('/user/{user:rsi_handle}/promotion-offers', [MemberPromotionController::class, 'store'])
    ->middleware(['auth', 'rsi.verified'])
    ->name('member.promotions.store');

Route::post('/user/{user:rsi_handle}/promotion-offers/{promotionOffer}/cancel', [MemberPromotionController::class, 'cancel'])
    ->middleware(['auth', 'rsi.verified'])
    ->name('member.promotions.cancel');

Route::post('/user/{user:rsi_handle}/demote', [MemberPromotionController::class, 'demote'])
    ->middleware(['auth', 'rsi.verified'])
    ->name('member.promotions.demote');

Route::get('/members', [MemberDirectoryController::class, 'index'])
    ->middleware(['auth', 'rsi.verified'])
    ->name('members.index');

Route::get('/settings', [MemberSettingsController::class, 'show'])
    ->middleware(['auth', 'rsi.verified'])
    ->name('settings.index');

Route::post('/settings/discord-roles/sync', [MemberDiscordRoleController::class, 'sync'])
    ->middleware(['auth', 'rsi.verified'])
    ->name('settings.discord-roles.sync');

Route::put('/settings/discord-roles', [MemberDiscordRoleController::class, 'update'])
    ->middleware(['auth', 'rsi.verified'])
    ->name('settings.discord-roles.update');

Route::post('/role-preview', [RolePreviewController::class, 'update'])
    ->middleware(['auth', 'rsi.verified'])
    ->name('role-preview.update');

Route::middleware(['auth', 'rsi.verified', 'can:access-ledger'])->group(function () {
    Route::get('/ledger', [MemberLedgerController::class, 'index'])
        ->name('ledger.index');

    Route::get('/organization/ledger', [OrganizationLedgerController::class, 'index'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger');

    Route::post('/ledger/transactions', [LedgerTransactionController::class, 'store'])
        ->name('ledger.transactions.store');
    Route::post('/ledger/transfers', [LedgerTransactionController::class, 'transfer'])
        ->name('ledger.transactions.transfer');
    Route::post('/ledger/transfers/{transferRequest}/approve', [LedgerTransactionController::class, 'approveTransfer'])
        ->name('ledger.transactions.transfer.approve');
    Route::post('/ledger/transfers/{transferRequest}/reject', [LedgerTransactionController::class, 'rejectTransfer'])
        ->name('ledger.transactions.transfer.reject');
    Route::post('/ledger/transfers/{transferRequest}/reverse', [LedgerTransactionController::class, 'reverseTransfer'])
        ->name('ledger.transactions.transfer.reverse');
    Route::put('/ledger/transactions/{transaction}', [LedgerTransactionController::class, 'update'])
        ->name('ledger.transactions.update');
    Route::delete('/ledger/transactions/{transaction}', [LedgerTransactionController::class, 'destroy'])
        ->name('ledger.transactions.destroy');

    Route::post('/ledger/trades', [LedgerTradeController::class, 'store'])
        ->name('ledger.trades.store');
    Route::put('/ledger/trades/{trade}', [LedgerTradeController::class, 'update'])
        ->name('ledger.trades.update');
    Route::delete('/ledger/trades/{trade}', [LedgerTradeController::class, 'destroy'])
        ->name('ledger.trades.destroy');

    Route::post('/ledger/inventory', [LedgerInventoryController::class, 'store'])
        ->name('ledger.inventory.store');
    Route::post('/ledger/inventory/transfers', [LedgerInventoryController::class, 'transfer'])
        ->name('ledger.inventory.transfer');
    Route::post('/ledger/inventory/transfers/{transferRequest}/approve', [LedgerInventoryController::class, 'approveTransfer'])
        ->name('ledger.inventory.transfer.approve');
    Route::post('/ledger/inventory/transfers/{transferRequest}/reject', [LedgerInventoryController::class, 'rejectTransfer'])
        ->name('ledger.inventory.transfer.reject');
    Route::put('/ledger/inventory/{inventoryItem}', [LedgerInventoryController::class, 'update'])
        ->name('ledger.inventory.update');
    Route::delete('/ledger/inventory/{inventoryItem}', [LedgerInventoryController::class, 'destroy'])
        ->name('ledger.inventory.destroy');

    Route::post('/ledger/ships', [LedgerShipAssetController::class, 'store'])
        ->name('ledger.ships.store');
    Route::put('/ledger/ships/{ship}', [LedgerShipAssetController::class, 'update'])
        ->name('ledger.ships.update');
    Route::delete('/ledger/ships/{ship}', [LedgerShipAssetController::class, 'destroy'])
        ->name('ledger.ships.destroy');

    Route::post('/organization/ledger/transactions', [OrganizationLedgerTransactionController::class, 'store'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.transactions.store');
    Route::post('/organization/ledger/transfers', [OrganizationLedgerTransactionController::class, 'transfer'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.transactions.transfer');
    Route::post('/organization/ledger/transfers/{transferRequest}/approve', [OrganizationLedgerTransactionController::class, 'approveTransfer'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.transactions.transfer.approve');
    Route::post('/organization/ledger/transfers/{transferRequest}/reject', [OrganizationLedgerTransactionController::class, 'rejectTransfer'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.transactions.transfer.reject');
    Route::post('/organization/ledger/transfers/{transferRequest}/reverse', [OrganizationLedgerTransactionController::class, 'reverseTransfer'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.transactions.transfer.reverse');
    Route::put('/organization/ledger/transactions/{transaction}', [OrganizationLedgerTransactionController::class, 'update'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.transactions.update');
    Route::delete('/organization/ledger/transactions/{transaction}', [OrganizationLedgerTransactionController::class, 'destroy'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.transactions.destroy');

    Route::post('/organization/ledger/trades', [OrganizationLedgerTradeController::class, 'store'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.trades.store');
    Route::put('/organization/ledger/trades/{trade}', [OrganizationLedgerTradeController::class, 'update'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.trades.update');
    Route::delete('/organization/ledger/trades/{trade}', [OrganizationLedgerTradeController::class, 'destroy'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.trades.destroy');

    Route::post('/organization/ledger/inventory', [OrganizationLedgerInventoryController::class, 'store'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.inventory.store');
    Route::post('/organization/ledger/inventory/transfers', [OrganizationLedgerInventoryController::class, 'transfer'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.inventory.transfer');
    Route::post('/organization/ledger/inventory/transfers/{transferRequest}/approve', [OrganizationLedgerInventoryController::class, 'approveTransfer'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.inventory.transfer.approve');
    Route::post('/organization/ledger/inventory/transfers/{transferRequest}/reject', [OrganizationLedgerInventoryController::class, 'rejectTransfer'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.inventory.transfer.reject');
    Route::put('/organization/ledger/inventory/{inventoryItem}', [OrganizationLedgerInventoryController::class, 'update'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.inventory.update');
    Route::delete('/organization/ledger/inventory/{inventoryItem}', [OrganizationLedgerInventoryController::class, 'destroy'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.inventory.destroy');

    Route::post('/organization/ledger/ships', [OrganizationLedgerShipAssetController::class, 'store'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.ships.store');
    Route::put('/organization/ledger/ships/{ship}', [OrganizationLedgerShipAssetController::class, 'update'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.ships.update');
    Route::delete('/organization/ledger/ships/{ship}', [OrganizationLedgerShipAssetController::class, 'destroy'])
        ->middleware('can:manage-org-ledger')
        ->name('organization.ledger.ships.destroy');
});

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
        'region',
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
        'site_theme',
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
    Route::put('/operations/{operation}/after-action-report',
        [OperationPageController::class, 'updateAfterActionReport'])
        ->name('operations.aar.update');
    Route::put('/operations/{operation}/settlement',
        [OperationPageController::class, 'updateSettlement'])
        ->name('operations.settlement.update');
    Route::post('/operations/{operation}/settlement/finalize',
        [OperationPageController::class, 'finalizeSettlement'])
        ->name('operations.settlement.finalize');
    Route::post('/operations/{operation}/settlement/reopen',
        [OperationPageController::class, 'reopenSettlement'])
        ->name('operations.settlement.reopen');
    Route::get('/operations/{operation}/settlement/export',
        [OperationPageController::class, 'exportSettlement'])
        ->name('operations.settlement.export');
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
    ->scopeBindings()
    ->group(function () {
        Route::get('/', [ArchiveController::class, 'index'])->name('index');
        Route::get('/category/{category:slug}', [ArchiveController::class, 'category'])->name('category');
        Route::get('/category/{category:slug}/{entry:slug}', [ArchiveController::class, 'categoryEntry'])->name('category.entry');
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

Route::get('/squadrons/{squadron}/ledger', [SquadronLedgerController::class, 'showById'])
    ->whereNumber('squadron')
    ->middleware(['auth', 'rsi.verified'])
    ->name('squadrons.ledgerById');

Route::middleware(['auth', 'rsi.verified', 'can:access-ledger'])->group(function () {
    Route::post('/squadrons/{squadron}/ledger/transactions', [SquadronLedgerTransactionController::class, 'store'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.transactions.store');
    Route::post('/squadrons/{squadron}/ledger/transfers', [SquadronLedgerTransactionController::class, 'transfer'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.transactions.transfer');
    Route::post('/squadrons/{squadron}/ledger/transfers/{transferRequest}/approve', [SquadronLedgerTransactionController::class, 'approveTransfer'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.transactions.transfer.approve');
    Route::post('/squadrons/{squadron}/ledger/transfers/{transferRequest}/reject', [SquadronLedgerTransactionController::class, 'rejectTransfer'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.transactions.transfer.reject');
    Route::post('/squadrons/{squadron}/ledger/transfers/{transferRequest}/reverse', [SquadronLedgerTransactionController::class, 'reverseTransfer'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.transactions.transfer.reverse');
    Route::put('/squadrons/{squadron}/ledger/transactions/{transaction}', [SquadronLedgerTransactionController::class, 'update'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.transactions.update');
    Route::delete('/squadrons/{squadron}/ledger/transactions/{transaction}', [SquadronLedgerTransactionController::class, 'destroy'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.transactions.destroy');

    Route::post('/squadrons/{squadron}/ledger/trades', [SquadronLedgerTradeController::class, 'store'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.trades.store');
    Route::put('/squadrons/{squadron}/ledger/trades/{trade}', [SquadronLedgerTradeController::class, 'update'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.trades.update');
    Route::delete('/squadrons/{squadron}/ledger/trades/{trade}', [SquadronLedgerTradeController::class, 'destroy'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.trades.destroy');

    Route::post('/squadrons/{squadron}/ledger/inventory', [SquadronLedgerInventoryController::class, 'store'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.inventory.store');
    Route::post('/squadrons/{squadron}/ledger/inventory/transfers', [SquadronLedgerInventoryController::class, 'transfer'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.inventory.transfer');
    Route::post('/squadrons/{squadron}/ledger/inventory/transfers/{transferRequest}/approve', [SquadronLedgerInventoryController::class, 'approveTransfer'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.inventory.transfer.approve');
    Route::post('/squadrons/{squadron}/ledger/inventory/transfers/{transferRequest}/reject', [SquadronLedgerInventoryController::class, 'rejectTransfer'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.inventory.transfer.reject');
    Route::put('/squadrons/{squadron}/ledger/inventory/{inventoryItem}', [SquadronLedgerInventoryController::class, 'update'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.inventory.update');
    Route::delete('/squadrons/{squadron}/ledger/inventory/{inventoryItem}', [SquadronLedgerInventoryController::class, 'destroy'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.inventory.destroy');

    Route::post('/squadrons/{squadron}/ledger/ships', [SquadronLedgerShipAssetController::class, 'store'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.ships.store');
    Route::put('/squadrons/{squadron}/ledger/ships/{ship}', [SquadronLedgerShipAssetController::class, 'update'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.ships.update');
    Route::delete('/squadrons/{squadron}/ledger/ships/{ship}', [SquadronLedgerShipAssetController::class, 'destroy'])
        ->whereNumber('squadron')
        ->name('squadrons.ledger.ships.destroy');
});

Route::get('/squadrons/{squadron:slug}', [SquadronPageController::class, 'show'])
    ->middleware(['auth', 'rsi.verified'])
    ->name('squadrons.show');

Route::get('/squadrons/{squadron:slug}/ledger', [SquadronLedgerController::class, 'show'])
    ->middleware(['auth', 'rsi.verified'])
    ->name('squadrons.ledger');

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

        Route::get('/uex/resources/{resource}', [AdminUexDataController::class, 'index'])
            ->name('admin.uex.resources.index');

        Route::post('/uex/sync', [AdminUexSyncController::class, 'store'])
            ->name('admin.uex.sync');

        Route::post('/ledger/wipes', [AdminLedgerWipeController::class, 'store'])
            ->name('admin.ledger.wipes.store');

        Route::post('/ledger/wipes/{wipeCycle}/activate', [AdminLedgerWipeController::class, 'activate'])
            ->name('admin.ledger.wipes.activate');

        Route::post('/ledger/wipes/{wipeCycle}/close', [AdminLedgerWipeController::class, 'close'])
            ->name('admin.ledger.wipes.close');

        Route::post('/ledger/wipes/{wipeCycle}/rename', [AdminLedgerWipeController::class, 'rename'])
            ->name('admin.ledger.wipes.rename');
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

        Route::post('/users/clear-remembered-sessions', [AdminUserController::class, 'clearRememberedSessions'])
            ->name('admin.users.clearRememberedSessions');

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

require __DIR__ . '/admin_archive.php';

Route::fallback(function () {
    return Inertia::render('Error', [
        'status' => 404,
    ])->toResponse(request())->setStatusCode(404);
})->withoutMiddleware([
    \App\Http\Middleware\ForceDiscordAuth::class,
    \App\Http\Middleware\EnforceMaxAuthAge::class,
]);
