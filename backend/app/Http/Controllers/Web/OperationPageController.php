<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\Squadron;
use App\Http\Requests\Operations\OperationStoreRequest;
use App\Http\Requests\Operations\OperationUpdateRequest;

// Services & Presenters
use App\Domain\Operations\Services\OperationService;
use App\Domain\Operations\Services\ParticipantService;
use App\Domain\Operations\Presenters\OperationPresenter;
use App\Domain\Operations\Queries\OperationQuery;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;

class OperationPageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OperationService    $service,
        protected OperationQuery      $query,
        protected ParticipantService  $participants
    ) {}

    /* ============================================================
     | INDEX (ALL OPS)
     * ============================================================ */
    public function index(Request $request)
    {
        $now = now();

        $operations = Operation::query()
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 2 WHEN starts_at >= ? THEN 0 ELSE 1 END', [$now])
            ->orderByRaw('CASE WHEN starts_at >= ? THEN starts_at END ASC', [$now])
            ->orderByRaw('CASE WHEN starts_at < ? THEN starts_at END DESC', [$now])
            ->with(['squadron.leader', 'creator'])
            ->paginate(12)
            ->withQueryString();

        $operations->setCollection(
            $operations->getCollection()
                ->map(fn ($op) => OperationPresenter::make($op)->summary())
        );

        return Inertia::render('Operations/MissionsIndex', [
            'operations' => $operations,
        ]);
    }

    /* ============================================================
     | SHOW SINGLE OPERATION
     * ============================================================ */
    public function show(Request $request, Operation $operation)
    {
        $this->authorize('view', $operation);

        // Load full graph for display
        $operation = $this->service->loadGraph($operation);
        $user = $request->user();

        $participants = $operation->participants;

        // Group participants by slot
        $participantsBySlot = $participants->groupBy(function ($p) {
            return $p->slot ?: 'unassigned';
        });

        $unassigned = $participants->filter(fn ($p) => !$p->slot)->values();

        $currentParticipant = $participants->firstWhere('user_id', $user->id);

        return Inertia::render('Operations/MissionShow', [
            'operation'              => OperationPresenter::make($operation)->full(),
            'authUser'               => $user,
            'participants'           => $participants,
            'participantsBySlot'     => $participantsBySlot,
            'unassignedParticipants' => $unassigned,
            'currentParticipant'     => $currentParticipant,
        ]);
    }

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

        $startsAt = $operation->starts_at?->copy()->utc();
        if (!$startsAt) {
            abort(404);
        }

        $endsAt = $operation->ends_at?->copy()->utc() ?? $startsAt->copy()->addHour();

        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $uid = "operation-{$operation->id}@{$host}";

        $summary = $this->escapeIcsText($operation->title ?: "Operation #{$operation->id}");

        $descriptionParts = array_values(array_filter([
            $operation->description,
            $operation->notes,
            route('operations.show', $operation->id),
        ]));
        $description = $this->escapeIcsText(implode("\n\n", $descriptionParts));

        $dtstamp = now()->utc()->format('Ymd\\THis\\Z');
        $dtstart = $startsAt->format('Ymd\\THis\\Z');
        $dtend = $endsAt->format('Ymd\\THis\\Z');

        $created = $operation->created_at?->copy()->utc()->format('Ymd\\THis\\Z');
        $lastModified = $operation->updated_at?->copy()->utc()->format('Ymd\\THis\\Z');

        $status = $operation->isCanceled() ? 'CANCELLED' : 'CONFIRMED';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//RVG//Operations//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$dtstamp}",
            "DTSTART:{$dtstart}",
            "DTEND:{$dtend}",
            "SUMMARY:{$summary}",
            "DESCRIPTION:{$description}",
            'CLASS:PUBLIC',
            "STATUS:{$status}",
        ];

        if ($created) {
            $lines[] = "CREATED:{$created}";
        }

        if ($lastModified) {
            $lines[] = "LAST-MODIFIED:{$lastModified}";
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        $folded = [];
        foreach ($lines as $line) {
            foreach ($this->foldIcsLine($line) as $l) {
                $folded[] = $l;
            }
        }

        $ics = implode("\r\n", $folded) . "\r\n";

        $base = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($operation->title ?: "operation-{$operation->id}"));
        $base = trim($base, '-');
        if ($base === '') {
            $base = "operation-{$operation->id}";
        }
        $filename = $base . '.ics';

        return response($ics, 200)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /* ============================================================
     | CREATE
     * ============================================================ */
    public function createGlobal()
    {
        $this->authorize('create', Operation::class);

        return Inertia::render('Operations/MissionEditor', [
            'mission'    => null,
            'squadronId' => null,
        ]);
    }

    public function storeGlobal(OperationStoreRequest $request)
    {
        $this->authorize('create', Operation::class);

        $operation = $this->service->create($request->validated(), null);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make($operation)->full(),
                ],
            ], 201);
        }

        return Inertia::location(route('operations.show', $operation->id));
    }

    public function create($squadronId)
    {
        $this->authorize('create', [Operation::class, Squadron::findOrFail((int) $squadronId)]);

        return Inertia::render('Operations/MissionEditor', [
            'mission'    => null,
            'squadronId' => (int) $squadronId,
        ]);
    }

    public function store(OperationStoreRequest $request, Squadron $squadron)
    {
        $this->authorize('create', [Operation::class, $squadron]);

        $operation = $this->service->create($request->validated(), $squadron);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make($operation)->full(),
                ],
            ], 201);
        }

        return Inertia::location(route('operations.show', $operation->id));
    }

    /* ============================================================
     | EDIT / UPDATE
     * ============================================================ */
    public function edit(Operation $operation)
    {
        $this->authorize('update', $operation);

        return Inertia::render('Operations/MissionEditor', [
            'mission'    => OperationPresenter::make($operation)->form(),
            'squadronId' => $operation->squadron_id,
        ]);
    }

    public function editData(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        return response()->json([
            'status' => 'ok',
            'payload' => [
                'mission' => OperationPresenter::make($operation)->form(),
                'squadronId' => $operation->squadron_id,
            ],
        ]);
    }

    public function update(OperationUpdateRequest $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $updated = $this->service->update($operation, $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make($updated)->full(),
                ],
            ]);
        }

        return redirect()
            ->route('operations.show', $updated->id)
            ->with('success', 'Operation updated.');
    }

    /* ============================================================
     | MEMBER INDEX (visible ops)
     * ============================================================ */
    public function memberIndex(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('operations.index');
        }

        $operations = $this->query->forUser($user)
            ->withQueryString();

        return Inertia::render('Operations/MemberIndex', [
            'operations' => $operations,
        ]);
    }

    /* ============================================================
     | PUBLISH
     * ============================================================ */
    public function publish(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        Log::info("🟢 publish() endpoint hit for operation {$operation->id}");

        $updated = $this->service->transition($operation, 'published');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make($updated)->full(),
                ],
            ]);
        }

        return redirect()
            ->route('operations.show', $operation->id)
            ->with('success', 'Operation published successfully.');
    }

    public function start(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $updated = $this->service->transition($operation, 'in_progress');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make($updated)->full(),
                ],
            ]);
        }

        return redirect()
            ->route('operations.show', $operation->id)
            ->with('success', 'Operation started.');
    }

    public function complete(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $updated = $this->service->transition($operation, 'completed');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make($updated)->full(),
                ],
            ]);
        }

        return redirect()
            ->route('operations.show', $operation->id)
            ->with('success', 'Operation completed.');
    }

    public function cancel(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $updated = $this->service->transition($operation, 'canceled', $data['reason'] ?? null);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'payload' => [
                    'operation' => OperationPresenter::make($updated)->full(),
                ],
            ]);
        }

        return redirect()
            ->route('operations.show', $operation->id)
            ->with('success', 'Operation canceled.');
    }

    public function showData(Request $request, Operation $operation)
    {
        $operation->load([
            'squadron',
            'creator',
            'participants.user',
        ]);

        $participants = $operation->participants;

        $participantsBySlot = $participants
            ->whereNotNull('slot')
            ->groupBy('slot');

        $unassignedParticipants = $participants
            ->whereNull('slot')
            ->values();

        $currentParticipant = $participants
            ->firstWhere('user_id', $request->user()?->getAuthIdentifier());

        return response()->json([
            'operation' => $operation,
            'participants' => $participants,
            'participantsBySlot' => $participantsBySlot,
            'unassignedParticipants' => $unassignedParticipants,
            'currentParticipant' => $currentParticipant,
        ]);
    }

    /* ============================================================
     | DELETE
     * ============================================================ */
    public function destroy(Operation $operation)
    {
        $this->authorize('delete', $operation);

        $operation->delete();

        return back()->with('success', 'Operation deleted.');
    }

    protected function escapeIcsText(?string $value): string
    {
        $value = $value ?? '';
        $value = str_replace("\r\n", "\n", $value);
        $value = str_replace("\r", "\n", $value);
        $value = str_replace("\\", "\\\\", $value);
        $value = str_replace(";", "\\;", $value);
        $value = str_replace(",", "\\,", $value);
        $value = str_replace("\n", "\\n", $value);
        return $value;
    }

    protected function foldIcsLine(string $line): array
    {
        $max = 75;
        if (strlen($line) <= $max) {
            return [$line];
        }

        $out = [];
        $out[] = substr($line, 0, $max);
        $rest = substr($line, $max);

        while ($rest !== '') {
            $out[] = ' ' . substr($rest, 0, $max - 1);
            $rest = substr($rest, $max - 1);
        }

        return $out;
    }

    /* ============================================================
     | PARTICIPANT ACTIONS (JOIN / LEAVE / UPDATE SLOT)
     * ============================================================ */

    public function join(Request $request, Operation $operation)
    {
        $this->authorize('view', $operation);

        $data = $request->validate([
            'slot'              => 'nullable|string|max:255',
            'notes'             => 'nullable|string|max:500',
            'operation_role_id' => 'nullable|exists:operation_roles,id',
        ]);

        if (array_key_exists('slot', $data) && $data['slot'] === '') {
            $data['slot'] = null;
        }

        $this->participants->join($operation, $request->user(), $data);

        return redirect()
            ->route('operations.show', $operation->id)
            ->with('reload', true);
    }

    public function leave(Request $request, Operation $operation)
    {
        $this->authorize('view', $operation);

        $this->participants->leave($operation, $request->user());

        return redirect()
            ->route('operations.show', $operation->id)
            ->with('reload', true);
    }

    public function updateSlot(
        Request $request,
        Operation $operation,
        OperationParticipant $participant
    ) {
        if ($participant->operation_id !== $operation->id) {
            abort(404);
        }

        if ($participant->user_id !== $request->user()->id) {
            $this->authorize('manageMembers', $operation);
        }

        $data = $request->validate([
            'slot'              => 'nullable|string|max:255',
            'operation_role_id' => 'nullable|exists:operation_roles,id',
        ]);

        if (array_key_exists('slot', $data) && $data['slot'] === '') {
            $data['slot'] = null;
        }

        $this->participants->updateSlot($operation, $participant, $data);

        return redirect()
            ->route('operations.show', $operation->id)
            ->with('reload', true);
    }
}
