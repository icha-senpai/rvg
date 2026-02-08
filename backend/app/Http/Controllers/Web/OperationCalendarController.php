<?php

namespace App\Http\Controllers\Web;

use App\Domain\Operations\Services\OperationCalendarService;
use App\Http\Controllers\Controller;
use App\Models\Operation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OperationCalendarController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OperationCalendarService $calendar
    ) {}

    public function calendar(Request $request, Operation $operation)
    {
        if ($request->user()) {
            $this->authorize('view', $operation);
        } else {
            if ($operation->visibility !== 'open') {
                abort(403);
            }
        }

        $operation->loadMissing(['squadron', 'creator']);

        try {
            $ics = $this->calendar->makeIcs($operation);
        } catch (\RuntimeException $e) {
            abort(404);
        }

        $filename = $this->calendar->makeFilename($operation);

        return response($ics, 200)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
