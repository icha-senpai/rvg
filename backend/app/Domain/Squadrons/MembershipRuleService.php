<?php

namespace App\Domain\Squadrons;

use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Centralizes reusable squadron membership invariants and lookup helpers.
 *
 * The membership domain has several flows that all need the same answers: does a
 * user already belong to a squadron, does a record belong to the squadron being
 * managed, what role should an empty payload become, and has the squadron hit
 * its lieutenant cap. Keeping those rules here prevents drift between the
 * lifecycle, admin, promotion, and access-control layers.
 */
class MembershipRuleService
{
    /**
     * Return the most recent membership row for the given user in the squadron.
     */
    public function membershipForUser(Squadron $squadron, User $user): ?SquadronMember
    {
        return $this->membershipForUserId($squadron, (int) $user->id);
    }

    /**
     * Return the most recent membership row for the given user id in the squadron.
     */
    public function membershipForUserId(Squadron $squadron, int $userId): ?SquadronMember
    {
        return SquadronMember::query()
            ->where('user_id', $userId)
            ->where('squadron_id', $squadron->id)
            ->latest('joined_at')
            ->first();
    }

    /**
     * Require that the given user currently has a membership row in the squadron.
     */
    public function requireMembershipForUser(
        Squadron $squadron,
        User $user,
        string $field = 'member',
        string $message = 'Not a member of this squadron.'
    ): SquadronMember {
        $member = $this->membershipForUser($squadron, $user);

        if (! $member) {
            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }

        return $member;
    }

    /**
     * Shared one-squadron-at-a-time invariant used by self-service joins.
     */
    public function assertUserCanJoinSingleSquadron(User $user): void
    {
        $this->assertUserHasNoSquadronMemberships((int) $user->id, 'member', 'Already in a squadron.');
    }

    /**
     * Shared one-squadron-at-a-time invariant used by direct admin adds.
     */
    public function assertUserCanBeAddedToSquadron(int $userId): void
    {
        $this->assertUserHasNoSquadronMemberships($userId, 'user_id', 'User already in a squadron.');
    }

    /**
     * Ensure the account has no existing membership rows anywhere before a new
     * squadron membership is created.
     */
    public function assertUserHasNoSquadronMemberships(int $userId, string $field, string $message): void
    {
        $already = SquadronMember::query()
            ->where('user_id', $userId)
            ->exists();

        if ($already) {
            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }
    }

    /**
     * Prevent duplicate membership creation inside the same squadron workflow.
     */
    public function assertUserNotAlreadyInSquadron(Squadron $squadron, int $userId): void
    {
        if ($this->membershipForUserId($squadron, $userId)) {
            throw ValidationException::withMessages([
                'user_id' => 'User already in squadron.',
            ]);
        }
    }

    /**
     * Ensure a caller is mutating a membership row that actually belongs to the
     * squadron currently being managed.
     */
    public function assertMemberBelongsToSquadron(Squadron $squadron, SquadronMember $member): void
    {
        if ((int) $member->squadron_id !== (int) $squadron->id) {
            throw ValidationException::withMessages([
                'member' => 'Member does not belong to this squadron.',
            ]);
        }
    }

    /**
     * Normalize empty or literal "null" payloads into the baseline member role.
     */
    public function normalizeRole(?string $role): string
    {
        if ($role === null || $role === '' || $role === 'null') {
            return SquadronMember::ROLE_MEMBER;
        }

        return $role;
    }

    /**
     * Count the current active lieutenants for the squadron.
     */
    public function lieutenantCount(Squadron $squadron): int
    {
        return SquadronMember::query()
            ->where('squadron_id', $squadron->id)
            ->where('role', SquadronMember::ROLE_LIEUTENANT)
            ->where('membership_status', SquadronMember::STATUS_ACTIVE)
            ->count();
    }

    /**
     * Return whether the squadron can promote one more lieutenant under the
     * current hard cap.
     */
    public function hasLieutenantCapacity(Squadron $squadron, int $maxLieutenants = 2): bool
    {
        return $this->lieutenantCount($squadron) < $maxLieutenants;
    }

    /**
     * Enforce the shared lieutenant-cap rule used by both authorization checks
     * and the actual promotion workflow.
     */
    public function assertLieutenantCapacity(Squadron $squadron, int $maxLieutenants = 2): void
    {
        if (! $this->hasLieutenantCapacity($squadron, $maxLieutenants)) {
            throw ValidationException::withMessages([
                'max_lt' => 'This squadron already has the maximum of two Lieutenants.',
            ]);
        }
    }
}
