<?php

namespace App\Domain\Squadrons;

use App\Models\Squadron;
use App\Models\User;
use App\Domain\States\SquadronState;
use Illuminate\Http\UploadedFile;

/**
 * Public squadron service used by controllers and other backend callers.
 *
 * Like MembershipService, this class stays intentionally small so controllers
 * can depend on one stable API while specialized collaborators handle admin,
 * settings, and emblem-specific rules.
 */
class SquadronService
{
    public function __construct(
        protected SquadronAdminService $admin,
        protected SquadronSettingsService $settings,
        protected SquadronEmblemService $emblems,
        protected SquadronDiscordService $discord,
    ) {}

    /**
     * List all squadrons with counts.
     */
    public function listAll()
    {
        return Squadron::with(['leader.roles'])
            ->with('emblem')
            ->withCount('members')
            ->orderBy('name')
            ->get();
    }

    /**
     * Return full squadron graph for web / API.
     */
    public function loadGraph(Squadron $squadron): Squadron
    {
        return $squadron->load([
            'members.user.roles',
            'leader.roles',
            'emblem',
        ]);
    }

    /**
     * Show a single squadron.
     */
    public function show(Squadron $squadron): Squadron
    {
        return $this->loadGraph($squadron);
    }

    /**
     * Create a new squadron with default state.
     */
    public function create(array $data): Squadron
    {
        // These defaults preserve the historical behavior of newly created
        // squadrons before any admin-only attributes are layered on top.
        $defaults = [
            'status'    => SquadronState::ACTIVE,
            'motto'     => null,
            'recruiting'=> true,
        ];

        return Squadron::create(array_merge($defaults, $data));
    }

    /**
     * Update a squadron.
     */
    public function update(Squadron $squadron, array $data): Squadron
    {
        return $this->settings->update($squadron, $data);
    }

    /**
     * Transition squadron state using the state machine.
     */
    public function transition(Squadron $squadron, string $toStatus): Squadron
    {
        return SquadronState::transition($squadron, $toStatus);
    }

    /**
     * Delete squadron (soft delete if model supports it).
     */
    public function delete(Squadron $squadron): void
    {
        $squadron->delete();
    }

    /**
     * Return the Discord ids whose shared Squadron role may change after this
     * squadron is deleted.
     */
    public function discordIdsAffectedByDeletion(Squadron $squadron): array
    {
        return $this->discord->discordIdsAffectedBySquadronDeletion($squadron);
    }

    /**
     * Load squadron members with the related user roles needed by management and
     * presentation layers.
     */
    public function members(Squadron $squadron)
    {
        return $squadron->members()->with(['user.roles'])->get();
    }

    /**
     * Update squadron settings fields that may include sanitized rich text.
     */
    public function updateSettings(Squadron $squadron, array $data): Squadron
    {
        return $this->settings->updateSettings($squadron, $data);
    }

    /**
     * Return the list of users who are allowed to lead a squadron.
     */
    public function eligibleLeaders()
    {
        return $this->admin->eligibleLeaders();
    }

    /**
     * Confirm that a proposed leader has sufficient org rank to lead a squadron.
     */
    public function assertEligibleLeader(?int $leaderId): void
    {
        $this->admin->assertEligibleLeader($leaderId);
    }

    /**
     * Create a squadron from the admin dashboard workflow.
     */
    public function createForAdmin(array $data): Squadron
    {
        return $this->admin->createForAdmin($data);
    }

    /**
     * Update a squadron from the admin dashboard workflow.
     */
    public function updateForAdmin(Squadron $squadron, array $data): Squadron
    {
        return $this->admin->updateForAdmin($squadron, $data);
    }

    /**
     * Save a manually linked Discord channel id for the squadron.
     */
    public function saveDiscordChannelConfiguration(Squadron $squadron, ?string $channelId): Squadron
    {
        return $this->discord->saveChannelConfiguration($squadron, $channelId);
    }

    /**
     * Repair legacy mismatches between the assigned squadron leader and the
     * roster rows stored in squadron_members.
     */
    public function repairRosterConsistency(Squadron $squadron): array
    {
        return $this->admin->repairRosterConsistency($squadron);
    }

    /**
     * Summarize whether the squadron's roster and leader assignment disagree.
     */
    public function rosterConsistencySummary(Squadron $squadron): array
    {
        return $this->admin->rosterConsistencySummary($squadron);
    }

    /**
     * Create a Discord channel for the squadron, then sync the roster.
     */
    public function createDiscordChannel(Squadron $squadron): array
    {
        return $this->discord->createChannelAndSync($squadron);
    }

    /**
     * Delete the linked Discord channel for a squadron when explicitly requested.
     */
    public function deleteDiscordChannel(Squadron $squadron): array
    {
        return $this->discord->deleteLinkedChannel($squadron);
    }

    /**
     * Sync the squadron roster to the configured Discord channel.
     */
    public function syncDiscord(Squadron $squadron): array
    {
        return $this->discord->syncSquadronChannelAccess($squadron);
    }

    /**
     * Run a full repair for the shared Squadron Discord role across the guild.
     */
    public function repairDiscordSharedRole(): array
    {
        return $this->discord->repairSharedSquadronRole();
    }

    /**
     * Re-evaluate the shared Squadron Discord role for users affected by a
     * deleted squadron.
     */
    public function syncSharedRoleAfterDeletion(iterable $discordIds): array
    {
        return $this->discord->syncSharedRoleAfterSquadronDeletion($discordIds);
    }

    /**
     * Normalize the admin save flow after a squadron record changes.
     */
    public function syncDiscordAfterAdminSave(Squadron $squadron, bool $createChannelIfMissing = false): array
    {
        return $this->discord->syncAfterAdminSave($squadron, $createChannelIfMissing);
    }

    /**
     * Upload a new emblem file for the squadron.
     */
    public function uploadEmblem(Squadron $squadron, UploadedFile $file): Squadron
    {
        return $this->emblems->uploadEmblem($squadron, $file);
    }

    /**
     * Assign a leader to a squadron.
     *
     * Leadership assignment lives in the admin service because it also needs to
     * keep the squadron membership table synchronized with the leader record.
     */
    public function assignLeader(Squadron $squadron, User $user): Squadron
    {
        $updated = $this->admin->assignLeader($squadron, $user);
        $this->discord->syncAfterMembershipChange($updated, [$user->discord_id ?? null]);

        return $updated;
    }

    /**
     * Decide whether the given user may change squadron emblem media.
     */
    public function canModifyEmblem(User $user, Squadron $squadron): bool
    {
        return $this->emblems->canModifyEmblem($user, $squadron);
    }

    /**
     * Select an existing media item as the active squadron emblem.
     */
    public function selectEmblem(Squadron $squadron, ?int $emblemMediaId): Squadron
    {
        return $this->emblems->selectEmblem($squadron, $emblemMediaId);
    }
}
