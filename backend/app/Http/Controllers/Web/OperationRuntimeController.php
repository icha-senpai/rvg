<?php

namespace App\Http\Controllers\Web;

use App\Application\Operations\OperationShowDataService;
use App\Application\Operations\OperationSettlementViewService;
use App\Domain\Operations\Services\OperationService;
use App\Domain\Operations\Services\OperationRuntimeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\OperationSettlementUpsertRequest;
use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OperationRuntimeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OperationShowDataService $showData,
        protected OperationSettlementViewService $settlementViews,
        protected OperationService $operations,
        protected OperationRuntimeService $runtime
    ) {}

    public function show(Request $request, Operation $operation)
    {
        $this->authorize('manage', $operation);

        return Inertia::render('Operations/OperationRun', [
            ...$this->showData->build($operation, $request->user()),
            'runtime' => $this->runtime->build($operation),
            'fundsPrep' => $this->settlementViews->prepPayload($operation),
            'authUser' => $request->user(),
            'verifiedMembers' => $this->showData->verifiedMembers(),
        ]);
    }

    public function sync(Request $request, Operation $operation)
    {
        $this->authorize('manage', $operation);

        $result = $this->runtime->syncLobby($operation, $request->user());

        $message = "Lobby synced. {$result['present_count']} present, {$result['no_show_count']} not here";

        if (($result['walk_in_count'] ?? 0) > 0) {
            $message .= ", {$result['walk_in_count']} walk-in";
        }

        $message .= '.';

        if (($result['unmatched_discord_ids'] ?? []) !== []) {
            $message .= ' Some Discord users could not be matched to Horizon accounts.';
        }

        return back()->with('success', $message);
    }

    public function updateParticipant(Request $request, Operation $operation, OperationParticipant $participant)
    {
        $this->authorize('manage', $operation);

        $data = $request->validate([
            'status' => 'required|string|in:waiting,present,operation_finished,no_show,excused,signed_off_before_start,technical_issue',
        ]);

        $this->runtime->updateParticipantStatus($operation, $participant, $data['status']);

        return back()->with('success', 'Runtime status updated.');
    }

    public function storeWalkIn(Request $request, Operation $operation)
    {
        $this->authorize('manage', $operation);

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $member = User::query()->findOrFail($data['user_id']);

        $this->runtime->addWalkIn($operation, $request->user(), $member);

        return back()->with('success', 'Walk-in added to the live roster.');
    }

    public function updateFundsPrep(OperationSettlementUpsertRequest $request, Operation $operation)
    {
        $this->authorize('manage', $operation);

        $this->operations->upsertRuntimeFundsPrep(
            $request->user(),
            $operation,
            $request->validated()
        );

        return back()->with('success', 'Funds prep draft saved.');
    }

    public function saveLayout(Request $request, Operation $operation)
    {
        $this->authorize('manage', $operation);

        $data = $request->validate([
            'channels' => 'array',
            'channels.*.id' => 'nullable|integer',
            'channels.*.client_key' => 'nullable|string|max:120',
            'channels.*.name' => 'required|string|max:100',
            'participant_assignments' => 'array',
            'participant_assignments.*.participant_id' => 'required|integer',
            'participant_assignments.*.channel_key' => 'nullable|string|max:120',
        ]);

        $this->runtime->saveLayout(
            $operation,
            $data['channels'] ?? [],
            $data['participant_assignments'] ?? [],
        );

        return back()->with('success', 'Operation Discord channel layout saved.');
    }

    public function syncChannels(Request $request, Operation $operation)
    {
        $this->authorize('manage', $operation);

        $data = $request->validate([
            'channels' => 'array',
            'channels.*.id' => 'nullable|integer',
            'channels.*.client_key' => 'nullable|string|max:120',
            'channels.*.name' => 'required|string|max:100',
            'participant_assignments' => 'array',
            'participant_assignments.*.participant_id' => 'required|integer',
            'participant_assignments.*.channel_key' => 'nullable|string|max:120',
        ]);

        $result = $this->runtime->syncLayoutAndDiscordChannels(
            $operation,
            $data['channels'] ?? [],
            $data['participant_assignments'] ?? [],
        );

        $message = "Discord channels synced. {$result['created_channel_count']} created, {$result['renamed_channel_count']} renamed, {$result['deleted_channel_count']} deleted, {$result['moved_member_count']} moved.";

        if (($result['missing_guild_member_ids'] ?? []) !== []) {
            $message .= ' Some Discord members could not be found in the guild.';
        }

        return back()->with('success', $message);
    }
}
