<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Domain\Squadrons\MembershipService;
use App\Domain\Squadrons\SquadronService;
use App\Models\Squadron;
use App\Models\SquadronMember;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;

/**
 * Handles admin-only squadron management screens and mutation actions.
 */
class AdminSquadronController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected SquadronService $squadrons,
        protected MembershipService $membership,
    ) {}

    /**
     * Render the admin squadron index with eligible leader choices.
     */
    public function index(Request $request)
    {
        $this->authorize('access-admin-panel');

        return Inertia::render('Admin/SquadronsIndex', [
            'squadrons'       => $this->squadrons->listAll(),
            'eligibleLeaders' => $this->squadrons->eligibleLeaders(),
        ]);
    }

    /**
     * Create a squadron from the admin dashboard.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate($this->squadronRules());

        $this->squadrons->assertEligibleLeader(isset($data['leader_id']) ? (int) $data['leader_id'] : null);
        $squadron = $this->squadrons->createForAdmin($data);
        $squadron = $this->squadrons->saveDiscordChannelConfiguration($squadron, $data['discord_channel_id'] ?? null);
        $result = $this->squadrons->syncDiscordAfterAdminSave(
            $squadron,
            $request->boolean('create_discord_channel') && blank($data['discord_channel_id'] ?? null)
        );

        return redirect()->route('admin.dashboard')
            ->with('success', $this->adminSaveMessage('created', $result));
    }

    /**
     * Update an existing squadron from the admin dashboard.
     */
    public function update(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate(array_merge(
            ['id' => ['required', 'exists:squadrons,id']],
            $this->squadronRules(ignoreSquadronId: (int) $request->input('id'))
        ));

        $this->squadrons->assertEligibleLeader(isset($data['leader_id']) ? (int) $data['leader_id'] : null);
        $squadron = $this->squadrons->updateForAdmin(Squadron::findOrFail($data['id']), $data);
        $squadron = $this->squadrons->saveDiscordChannelConfiguration($squadron, $data['discord_channel_id'] ?? null);
        $result = $this->squadrons->syncDiscordAfterAdminSave(
            $squadron,
            $request->boolean('create_discord_channel') && blank($data['discord_channel_id'] ?? null)
        );

        return redirect()->route('admin.dashboard')
            ->with('success', $this->adminSaveMessage('updated', $result));
    }

    /**
     * Delete a squadron from the admin dashboard.
     */
    public function destroy(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id' => ['required', 'exists:squadrons,id'],
            'delete_discord_channel' => ['nullable', 'boolean'],
        ]);

        $squadron = Squadron::findOrFail($data['id']);
        $affectedDiscordIds = $this->squadrons->discordIdsAffectedByDeletion($squadron);

        if ($request->boolean('delete_discord_channel')) {
            $discordResult = $this->squadrons->deleteDiscordChannel($squadron);

            if (! ($discordResult['ok'] ?? false)) {
                return redirect()->route('admin.dashboard')
                    ->with('success', 'Squadron delete stopped because the linked Discord channel could not be deleted: ' . ($discordResult['message'] ?? 'Unknown Discord error.'));
            }
        }

        $this->squadrons->delete($squadron);
        $sharedRoleResult = $this->squadrons->syncSharedRoleAfterDeletion($affectedDiscordIds);

        return redirect()->route('admin.dashboard')
            ->with('success', $this->deleteSuccessMessage(
                deletedDiscordChannel: $request->boolean('delete_discord_channel'),
                sharedRoleResult: $sharedRoleResult,
            ));
    }

    /**
     * Add a user directly to a squadron from the admin dashboard.
     */
    public function addMember(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'squadron_id' => ['required', 'exists:squadrons,id'],
            'user_id'     => ['required', 'exists:users,id'],
        ]);

        $this->membership->adminCreateMember(
            Squadron::findOrFail($data['squadron_id']),
            (int) $data['user_id']
        );

        return redirect()
            ->route('admin.squadrons.index')
            ->with('success', 'User added to squadron.');
    }

    /**
     * Update one squadron membership record from the admin dashboard.
     */
    public function updateMember(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id'                => ['required', 'exists:squadron_members,id'],
            'role'              => ['nullable', 'string', 'in:leader,lieutenant,null'],
            'membership_status' => ['required', 'string', 'in:active,pending,banned'],
        ]);

        $this->membership->adminUpdateMember(
            SquadronMember::findOrFail($data['id']),
            $data['role'] ?? null,
            $data['membership_status']
        );

        return redirect()
            ->route('admin.squadrons.index')
            ->with('success', 'Member updated.');
    }

    /**
     * Remove a squadron membership record from the admin dashboard.
     */
    public function removeMember(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id' => ['required', 'exists:squadron_members,id'],
        ]);

        $this->membership->adminDeleteMember(SquadronMember::findOrFail($data['id']));

        return redirect()
            ->route('admin.squadrons.index')
            ->with('success', 'Member removed.');
    }

    /**
     * Create a Discord text channel for an existing squadron and sync its roster.
     */
    public function createDiscordChannel(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id' => ['required', 'exists:squadrons,id'],
        ]);

        $squadron = Squadron::findOrFail($data['id']);
        $repair = $this->squadrons->repairRosterConsistency($squadron);
        $result = $this->squadrons->createDiscordChannel($repair['squadron']);

        return redirect()
            ->route('admin.dashboard')
            ->with('success', $result['ok']
                ? $this->prependRepairMessage(
                    $repair,
                    'Discord channel created and squadron channel access synced.'
                )
                : 'Discord setup needs repair: ' . $result['message']);
    }

    /**
     * Re-sync an existing squadron channel to the current Horizon roster.
     */
    public function syncDiscord(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id' => ['required', 'exists:squadrons,id'],
        ]);

        $squadron = Squadron::findOrFail($data['id']);
        $repair = $this->squadrons->repairRosterConsistency($squadron);
        $result = $this->squadrons->syncDiscord($repair['squadron']);

        return redirect()
            ->route('admin.dashboard')
            ->with('success', $result['ok']
                ? $this->prependRepairMessage(
                    $repair,
                    'Discord squadron channel access synced.'
                )
                : 'Discord setup needs repair: ' . $result['message']);
    }

    /**
     * Repair legacy roster mismatches for one squadron without running a
     * Discord sync yet.
     */
    public function repairRoster(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $data = $request->validate([
            'id' => ['required', 'exists:squadrons,id'],
        ]);

        $repair = $this->squadrons->repairRosterConsistency(Squadron::findOrFail($data['id']));

        return redirect()
            ->route('admin.dashboard')
            ->with('success', $repair['message'] ?? 'No roster repair was needed.');
    }

    /**
     * Run a full shared Squadron role repair across the Discord guild.
     */
    public function repairDiscordSharedRole(Request $request)
    {
        $this->authorize('create', Squadron::class);

        $result = $this->squadrons->repairDiscordSharedRole();

        return redirect()
            ->route('admin.dashboard')
            ->with('success', $result['ok']
                ? 'Shared Squadron role repair completed.'
                : 'Shared Squadron role repair needs attention: ' . $result['message']);
    }

    protected function squadronRules(?int $ignoreSquadronId = null): array
    {
        $slugRules = [
            'required',
            'string',
            'max:255',
            Rule::unique('squadrons', 'slug'),
        ];

        if ($ignoreSquadronId) {
            $slugRules[3] = Rule::unique('squadrons', 'slug')->ignore($ignoreSquadronId);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => $slugRules,
            'status' => ['required', 'in:active,inactive,disbanded'],
            'branch' => ['nullable', 'string', 'in:defence,industries,frontiers,lifeline'],
            'division' => ['nullable', 'string', 'in:marines,navy,airforce,procurement,logistics,construction,exploration,science,development,triage,recovery,medical'],
            'leader_id' => ['nullable', 'exists:users,id'],
            'discord_channel_id' => ['nullable', 'string', 'max:32', 'regex:/^\d+$/'],
            'create_discord_channel' => ['nullable', 'boolean'],
        ];
    }

    protected function adminSaveMessage(string $verb, array $result): string
    {
        if (($result['status'] ?? null) === Squadron::DISCORD_STATUS_NOT_LINKED) {
            return 'Squadron ' . $verb . '. Discord channel not linked yet.';
        }

        if (! ($result['ok'] ?? false)) {
            return 'Squadron ' . $verb . '. Discord setup needs repair: ' . ($result['message'] ?? 'Unknown Discord error.');
        }

        return 'Squadron ' . $verb . ' and Discord synced.';
    }

    protected function prependRepairMessage(array $repair, string $message): string
    {
        $repairMessage = trim((string) ($repair['message'] ?? ''));

        if ($repairMessage === '' || $repairMessage === 'No roster repair was needed.') {
            return $message;
        }

        return $repairMessage . ' ' . $message;
    }

    protected function deleteSuccessMessage(bool $deletedDiscordChannel, array $sharedRoleResult): string
    {
        $baseMessage = $deletedDiscordChannel
            ? 'Squadron and linked Discord channel deleted.'
            : 'Squadron deleted.';

        if (! ($sharedRoleResult['ok'] ?? false)) {
            return $baseMessage . ' Shared Squadron role repair needs attention: ' . ($sharedRoleResult['message'] ?? 'Unknown Discord error.');
        }

        return $baseMessage . ' Shared Squadron role synced.';
    }
}
