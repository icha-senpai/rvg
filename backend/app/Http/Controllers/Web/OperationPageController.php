<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Operation;
use Illuminate\Support\Facades\Route;

class OperationPageController extends Controller
{
    public function index()
    {
        $operations = Operation::query()
            ->with('squadron:id,name')
            ->orderByDesc('starts_at')
            ->get();

        return Inertia::render('Operations/MissionsIndex', [
            'operations' => $operations,
        ]);
    }

    public function show(Operation $operation)
    {
        $operation->load([
            'participants.user:id,name,display_name',
        ]);

        return Inertia::render('Operations/MissionShow', [
            'operation'    => $operation,
            'participants' => $operation->participants,
        ]);
    }

    public function create($squadronId)
    {
        return Inertia::render('Operations/MissionEditor', [
            'squadronId' => $squadronId,
        ]);
    }

    public function edit(Operation $operation)
    {
        return Inertia::render('Operations/MissionEditor', [
            'mission'     => $operation,
            'squadronId'  => $operation->squadron_id,
        ]);
    }
}
Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/operations', [OperationPageController::class, 'index'])
        ->name('operations.index');

    Route::get('/operations/{operation}', [OperationPageController::class, 'show'])
        ->name('operations.show');

    Route::get('/squadrons/{squadron}/operations/create', [OperationPageController::class, 'create'])
        ->name('operations.create');

    Route::get('/operations/{operation}/edit', [OperationPageController::class, 'edit'])
        ->name('operations.edit');
});