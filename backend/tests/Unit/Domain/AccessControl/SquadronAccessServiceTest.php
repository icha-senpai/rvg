<?php

namespace Tests\Unit\Domain\AccessControl;

use App\Domain\AccessControl\SquadronAccessService;
use App\Domain\Squadrons\Enums\SquadronMembershipStatus;
use App\Domain\Squadrons\Enums\SquadronRole;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SquadronAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_lieutenant_can_manage_members(): void
    {
        $service = app(SquadronAccessService::class);
        $user = User::factory()->create();
        $squadron = Squadron::create([
            'name' => 'Aegis',
            'slug' => 'aegis',
            'status' => 'active',
        ]);

        SquadronMember::create([
            'user_id' => $user->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMembershipStatus::Active->value,
            'role' => SquadronRole::Lieutenant->value,
            'joined_at' => now(),
        ]);

        $this->assertTrue($service->canManageSquadronMembers($user, $squadron));
    }

    public function test_active_lieutenant_can_manage_squadron_ledger_for_their_squadron(): void
    {
        $service = app(SquadronAccessService::class);
        $user = User::factory()->create();
        $squadron = Squadron::create([
            'name' => 'Aegis Ledger',
            'slug' => 'aegis-ledger',
            'status' => 'active',
        ]);

        SquadronMember::create([
            'user_id' => $user->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMembershipStatus::Active->value,
            'role' => SquadronRole::Lieutenant->value,
            'joined_at' => now(),
        ]);

        $this->assertTrue($service->canManageLedger($user, $squadron));
        $this->assertTrue($service->canViewLedger($user, $squadron));
    }

    public function test_active_squadron_leader_can_manage_squadron_ledger_for_their_squadron(): void
    {
        $service = app(SquadronAccessService::class);
        $user = User::factory()->create();
        $squadron = Squadron::create([
            'name' => 'Vanguard Ledger',
            'slug' => 'vanguard-ledger',
            'status' => 'active',
            'leader_id' => $user->id,
        ]);

        SquadronMember::create([
            'user_id' => $user->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMembershipStatus::Active->value,
            'role' => SquadronRole::Leader->value,
            'joined_at' => now(),
        ]);

        $this->assertTrue($service->canManageLedger($user, $squadron));
        $this->assertTrue($service->canViewLedger($user, $squadron));
    }

    public function test_non_member_cannot_manage_members_without_override(): void
    {
        $service = app(SquadronAccessService::class);
        $user = User::factory()->create();
        $squadron = Squadron::create([
            'name' => 'Echo',
            'slug' => 'echo',
            'status' => 'active',
        ]);

        $this->assertFalse($service->canManageSquadronMembers($user, $squadron));
    }
}
