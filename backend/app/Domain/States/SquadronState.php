<?php

namespace App\Domain\States;

use App\Models\Squadron;
use LogicException;

class SquadronState
{
    public const ACTIVE    = 'active';
    public const INACTIVE  = 'inactive';
    public const DISBANDED = 'disbanded';

    /**
     * All valid statuses for squadrons.
     */
    public const VALID_STATUSES = [
        self::ACTIVE,
        self::INACTIVE,
        self::DISBANDED,
    ];

    /**
     * Allowed transitions:
     *  active    → inactive, disbanded
     *  inactive  → active, disbanded
     *  disbanded → (terminal)
     */
    public const VALID_TRANSITIONS = [
        self::ACTIVE => [
            self::INACTIVE,
            self::DISBANDED,
        ],
        self::INACTIVE => [
            self::ACTIVE,
            self::DISBANDED,
        ],
        self::DISBANDED => [],
    ];

    /**
     * Determine if a transition is allowed.
     */
    public static function canTransition(Squadron $squadron, string $toStatus): bool
    {
        $from = $squadron->status;

        if (!in_array($from, self::VALID_STATUSES, true)) {
            return false;
        }

        if (!in_array($toStatus, self::VALID_STATUSES, true)) {
            return false;
        }

        if ($from === $toStatus) {
            return true;
        }

        return in_array($toStatus, self::VALID_TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Throw if the transition isn't allowed.
     */
    public static function assertCanTransition(Squadron $squadron, string $toStatus): void
    {
        if (!self::canTransition($squadron, $toStatus)) {
            $from = $squadron->status;
            throw new LogicException(
                "Invalid squadron state transition from [{$from}] to [{$toStatus}]"
            );
        }
    }

    /**
     * Perform the transition & apply side effects.
     */
    public static function transition(Squadron $squadron, string $toStatus): Squadron
    {
        self::assertCanTransition($squadron, $toStatus);

        $from = $squadron->status;
        $squadron->status = $toStatus;

        // Side effects:
        if ($toStatus === self::DISBANDED) {
            $squadron->recruiting = false;
            // optionally clear leader_id here if desired
            // $squadron->leader_id = null;
        }

        $squadron->save();

        return $squadron->fresh();
    }

    /**
     * Whether a state has no outgoing transitions.
     */
    public static function isTerminal(string $status): bool
    {
        return empty(self::VALID_TRANSITIONS[$status] ?? []);
    }
}
