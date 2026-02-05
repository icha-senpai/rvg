<?php

namespace App\Domain\Squadrons;

use App\Models\Squadron;
use App\Models\User;
use App\Models\SquadronMember;
use App\Domain\States\SquadronState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SquadronService
{
    /**
     * List all squadrons with counts.
     */
    public function listAll()
    {
        return Squadron::with('leader')
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
            'members.user',
            'leader',
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
        $squadron->update($data);
        return $squadron->fresh();
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
     * Get members.
     */
    public function members(Squadron $squadron)
    {
        return $squadron->members()->with('user')->get();
    }

    /**
     * Update settings.
     */
    public function updateSettings(Squadron $squadron, array $data): Squadron
    {
        $squadron->update($data);
        return $squadron->fresh();
    }

    /**
     * Upload emblem.
     */
    public function uploadEmblem(Squadron $squadron, UploadedFile $file): Squadron
    {
        $path = $file->store('squadrons', 'public');

        $squadron->update([
            'emblem_path' => $path,
        ]);

        return $squadron->fresh();
    }

    /**
     * Assign a leader to a squadron.
     * (MembershipService handles promotion logic)
     */
    public function assignLeader(Squadron $squadron, User $user): Squadron
    {
        // demote all previous leaders
        SquadronMember::where('squadron_id', $squadron->id)
            ->where('role', SquadronMember::ROLE_LEADER)
            ->update(['role' => SquadronMember::ROLE_MEMBER]);

        // ensure membership exists & promote
        SquadronMember::updateOrCreate(
            [
                'user_id'     => $user->id,
                'squadron_id' => $squadron->id,
            ],
            [
                'membership_status' => SquadronMember::STATUS_ACTIVE,
                'role'              => SquadronMember::ROLE_LEADER,
                'joined_at'         => now(),
            ]
        );

        // update squadron model
        $squadron->update(['leader_id' => $user->id]);

        return $squadron->fresh();
    }
}
