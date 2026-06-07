<?php

namespace Tests\Unit\Domain\AccessControl;

use App\Domain\AccessControl\SquadronMembershipReadService;
use App\Domain\Squadrons\Enums\SquadronMembershipStatus;
use App\Domain\Squadrons\Enums\SquadronRole;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SquadronMembershipReadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reuses_active_membership_lookup_for_repeated_checks(): void
    {
        $user = User::factory()->create();
        $squadron = Squadron::create([
            'name' => 'Apex',
            'slug' => 'apex',
            'status' => 'active',
        ]);

        SquadronMember::create([
            'user_id' => $user->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMembershipStatus::Active->value,
            'role' => SquadronRole::Leader->value,
            'joined_at' => now(),
        ]);

        $service = new class extends SquadronMembershipReadService {
            public int $resolvedMemberships = 0;

            protected function resolveActiveMembership(User $user, int $squadronId): ?SquadronMember
            {
                $this->resolvedMemberships++;

                return parent::resolveActiveMembership($user, $squadronId);
            }
        };

        $this->assertNotNull($service->activeMembership($user, $squadron));
        $this->assertTrue($service->isLeader($user, $squadron));
        $this->assertFalse($service->isLieutenant($user, $squadron));
        $this->assertNotNull($service->activeMembershipForId($user, $squadron->id));
        $this->assertSame(1, $service->resolvedMemberships);
    }
}
