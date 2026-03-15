<?php

namespace App\Domain\Squadrons;

use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;

/**
 * Public membership entry point used by controllers.
 *
 * This class stays intentionally small so web and API controllers can depend on
 * one stable service while the real work is split into focused collaborators.
 */
class MembershipService
{
    public function __construct(
        protected MembershipAdminService $admin,
        protected MembershipLifecycleService $lifecycle,
        protected MembershipPromotionService $promotion,
    ) {}

    /**
     * Admin / director adds a member directly to a squadron.
     */
    public function adminAddMember(Squadron $squadron, int $userId): SquadronMember
    {
        // Delegate the direct-add workflow to the admin-specific service so this
        // facade stays easy to read and safe to evolve.
        return $this->admin->adminAddMember($squadron, $userId);
    }

    /**
     * Create an active membership record from an admin context.
     */
    public function adminCreateMember(Squadron $squadron, int $userId): SquadronMember
    {
        return $this->admin->adminCreateMember($squadron, $userId);
    }

    /**
     * Update only the member status from an admin flow.
     */
    public function adminUpdateStatus(Squadron $squadron, SquadronMember $member, string $status): SquadronMember
    {
        return $this->admin->adminUpdateStatus($squadron, $member, $status);
    }

    /**
     * Update a member's role and status from the admin panel.
     */
    public function adminUpdateMember(SquadronMember $member, ?string $role, string $status): SquadronMember
    {
        return $this->admin->adminUpdateMember($member, $role, $status);
    }

    /**
     * Remove a member from a squadron from an admin flow.
     */
    public function adminRemoveMember(Squadron $squadron, SquadronMember $member): void
    {
        $this->admin->adminRemoveMember($squadron, $member);
    }

    /**
     * Delete a membership record directly.
     */
    public function adminDeleteMember(SquadronMember $member): void
    {
        $this->admin->adminDeleteMember($member);
    }

    /**
     * Let a user apply to or join a squadron through the self-service flow.
     */
    public function userJoin(Squadron $squadron, User $user): SquadronMember
    {
        return $this->lifecycle->userJoin($squadron, $user);
    }

    /**
     * Let a user leave their current squadron through the self-service flow.
     */
    public function userLeave(Squadron $squadron, User $user): void
    {
        $this->lifecycle->userLeave($squadron, $user);
    }

    /**
     * Update role and status from the squadron management screen.
     *
     * This path applies extra guard rails around self-demotion rules because it
     * is used by leaders and lieutenants inside the normal squadron UI.
     */
    public function updateMemberFromManage(
        Squadron $squadron,
        SquadronMember $member,
        ?string $role,
        string $status,
        User $actingUser
    ): SquadronMember {
        return $this->admin->updateMemberFromManage(
            $squadron,
            $member,
            $role,
            $status,
            $actingUser
        );
    }

    /**
     * Remove a member from the squadron management screen.
     *
     * The admin service enforces the "cannot remove yourself" and lieutenant vs
     * leader protections so controllers do not have to duplicate those rules.
     */
    public function removeMemberFromManage(
        Squadron $squadron,
        SquadronMember $member,
        User $actingUser
    ): void {
        $this->admin->removeMemberFromManage($squadron, $member, $actingUser);
    }

    /**
     * Promote a user to lieutenant while enforcing the squadron limit.
     */
    public function promoteLieutenant(Squadron $squadron, User $user): SquadronMember
    {
        // Promotions touch membership state, user roles, rank, and cached access
        // data, so the side effects live in a dedicated promotion service.
        return $this->promotion->promoteLieutenant($squadron, $user);
    }

    /**
     * Demote a lieutenant back to the regular member role.
     */
    public function demoteLieutenant(Squadron $squadron, User $user): SquadronMember
    {
        return $this->promotion->demoteLieutenant($squadron, $user);
    }
}
