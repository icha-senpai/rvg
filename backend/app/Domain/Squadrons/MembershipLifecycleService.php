<?php

namespace App\Domain\Squadrons;

use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;

/**
 * Handles self-service membership actions initiated by the user themselves.
 *
 * These flows stay intentionally small because they only need to guard the
 * user's own membership lifecycle, not cross-user admin rules.
 */
class MembershipLifecycleService
{
    public function __construct(
        protected MembershipRuleService $rules
    ) {}

    /**
     * Create a pending membership request for the current user.
     *
     * A user can only belong to one squadron at a time, so this check happens
     * before the new pending record is created.
     */
    public function userJoin(Squadron $squadron, User $user): SquadronMember
    {
        $this->rules->assertUserCanJoinSingleSquadron($user);

        return SquadronMember::create([
            'user_id' => $user->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_PENDING,
            'joined_at' => now(),
        ]);
    }

    /**
     * Let the current user leave the given squadron.
     *
     * The timestamp is stored before deletion so the system can preserve the fact
     * that the user left even though the active membership row is removed.
     */
    public function userLeave(Squadron $squadron, User $user): void
    {
        $member = $this->rules->requireMembershipForUser($squadron, $user);

        $member->update([
            'left_at' => now(),
        ]);

        $member->delete();
    }
}
