<?php

namespace Tests\Unit\Domain\Squadrons;

use App\Domain\Squadrons\Enums\SquadronMembershipStatus;
use App\Domain\Squadrons\Enums\SquadronRole;
use App\Domain\Squadrons\MembershipAdminService;
use App\Domain\Squadrons\MembershipLifecycleService;
use App\Domain\Squadrons\MembershipPromotionService;
use App\Models\Role;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MembershipServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_join_two_squadrons_at_once(): void
    {
        $service = app(MembershipLifecycleService::class);
        $user = User::factory()->create();
        $first = Squadron::create([
            'name' => 'Nova',
            'slug' => 'nova',
            'status' => 'active',
        ]);
        $second = Squadron::create([
            'name' => 'Atlas',
            'slug' => 'atlas',
            'status' => 'active',
        ]);

        $member = $service->userJoin($first, $user);

        $this->assertSame(SquadronMembershipStatus::Pending->value, $member->membership_status);

        $this->expectException(ValidationException::class);

        $service->userJoin($second, $user);
    }

    public function test_lieutenant_cannot_remove_the_squadron_leader_from_manage_flow(): void
    {
        $service = app(MembershipAdminService::class);
        $leader = User::factory()->create();
        $lieutenant = User::factory()->create();
        $squadron = Squadron::create([
            'name' => 'Vanguard',
            'slug' => 'vanguard',
            'status' => 'active',
            'leader_id' => $leader->id,
        ]);

        $leaderMembership = SquadronMember::create([
            'user_id' => $leader->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMembershipStatus::Active->value,
            'role' => SquadronRole::Leader->value,
            'joined_at' => now(),
        ]);

        SquadronMember::create([
            'user_id' => $lieutenant->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMembershipStatus::Active->value,
            'role' => SquadronRole::Lieutenant->value,
            'joined_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        $service->removeMemberFromManage($squadron, $leaderMembership, $lieutenant);
    }

    public function test_promote_and_demote_lieutenant_syncs_membership_and_roles(): void
    {
        $service = app(MembershipPromotionService::class);
        $user = User::factory()->create([
            'rank' => 'member',
            'rank_level' => 1,
        ]);
        $squadron = Squadron::create([
            'name' => 'Sentinel',
            'slug' => 'sentinel',
            'status' => 'active',
        ]);

        $memberRole = Role::create(['name' => 'Member', 'slug' => 'member']);
        $lieutenantRole = Role::create(['name' => 'Lieutenant', 'slug' => 'lieutenant']);

        $user->roles()->attach($memberRole->id);

        SquadronMember::create([
            'user_id' => $user->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMembershipStatus::Active->value,
            'role' => SquadronRole::Member->value,
            'joined_at' => now(),
        ]);

        $promoted = $service->promoteLieutenant($squadron, $user);

        $this->assertSame(SquadronRole::Lieutenant->value, $promoted->role);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'role_id' => $lieutenantRole->id,
        ]);

        $demoted = $service->demoteLieutenant($squadron, $user);

        $this->assertSame(SquadronRole::Member->value, $demoted->role);
        $this->assertDatabaseMissing('role_user', [
            'user_id' => $user->id,
            'role_id' => $lieutenantRole->id,
        ]);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'role_id' => $memberRole->id,
        ]);
    }
}
