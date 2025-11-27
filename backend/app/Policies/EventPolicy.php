<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Directors: org-level authority.
     * Uses your existing User::isDirector() helper if present.
     */
    private function isDirector(User $user): bool
    {
        if (method_exists($user, 'isDirector')) {
            return $user->isDirector();
        }

        return false;
    }

    /**
     * Squadron leadership for a specific event.
     * Uses the "main" active squadron membership on the user.
     */
    private function squadronLeadership(User $user, Event $event): bool
    {
        // This assumes you have a `squadron()` relation on User
        // that returns the active SquadronMember row.
        $member = $user->squadron;

        if (!$member) {
            return false;
        }

        if ($member->squadron_id !== $event->squadron_id) {
            return false;
        }

        // Adjust this list if your rank names differ
        $leadershipRanks = [
            'lieutenant',
            'commander',
            'wing_commander',
            'admiral',
            'grand_admiral',
        ];

        return in_array($member->rank ?? null, $leadershipRanks, true);
    }

    /* ------------------------------
       VIEW
       ------------------------------*/

    public function viewAny(User $user): bool
    {
        // For now, let any authenticated user see the event list.
        return true;
    }

    public function view(User $user, Event $event): bool
    {
        // Directors see everything
        if ($this->isDirector($user)) {
            return true;
        }

        // Open events are visible to all members
        if ($event->visibility === 'open') {
            return true;
        }

        // Otherwise, must belong to the same squadron
        $member = $user->squadron;

        return $member && $member->squadron_id === $event->squadron_id;
    }

    /* ------------------------------
       CREATE
       ------------------------------*/

    public function create(User $user): bool
    {
        // Directors can always create events
        if ($this->isDirector($user)) {
            return true;
        }

        // Allow Squadron leadership to create events
        $member = $user->squadron;

        if (!$member) {
            return false;
        }

        $creatorRanks = [
            'lieutenant',
            'commander',
            'wing_commander',
            'admiral',
            'grand_admiral',
        ];

        return in_array($member->rank ?? null, $creatorRanks, true);
    }

    /* ------------------------------
       UPDATE / DELETE
       ------------------------------*/

    public function update(User $user, Event $event): bool
    {
        // Directors override
        if ($this->isDirector($user)) {
            return true;
        }

        // Event creator always allowed
        if ($user->id === $event->created_by) {
            return true;
        }

        // Squadron leadership can edit
        return $this->squadronLeadership($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    /* ------------------------------
       MEMBER MANAGEMENT / STATS
       ------------------------------*/

    public function manageMembers(User $user, Event $event): bool
    {
        // Directors override
        if ($this->isDirector($user)) {
            return true;
        }

        // Squadron leadership
        return $this->squadronLeadership($user, $event);
    }

    public function adjustStats(User $user, Event $event): bool
    {
        return $this->manageMembers($user, $event);
    }

    /* ------------------------------
       MANAGE (status, roles, etc.)
       ------------------------------*/

    public function manage(User $user, Event $event): bool
    {
        // Directors override
        if ($this->isDirector($user)) {
            return true;
        }

        // Creator can manage their own event
        if ($user->id === $event->created_by) {
            return true;
        }

        // Squadron leadership can manage
        return $this->squadronLeadership($user, $event);
    }
}
