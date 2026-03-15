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
        return $this->admin->assignLeader($squadron, $user);
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
