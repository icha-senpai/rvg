<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use App\Models\Operation;

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

    public function create($squadron)
    {
        return Inertia::render('Operations/MissionEditor', [
            'squadronId' => (int) $squadron,
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
