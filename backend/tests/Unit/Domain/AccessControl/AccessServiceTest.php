<?php

namespace Tests\Unit\Domain\AccessControl;

use App\Domain\AccessControl\AccessService;
use App\Domain\Squadrons\Enums\SquadronMembershipStatus;
use App\Domain\Squadrons\Enums\SquadronRole;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_squadron_membership_delegates_to_membership_reader(): void
    {
        $user = User::factory()->create();
        $squadron = Squadron::create([
            'name' => 'Relay',
            'slug' => 'relay',
            'status' => 'active',
        ]);

        $membership = SquadronMember::create([
            'user_id' => $user->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMembershipStatus::Active->value,
            'role' => SquadronRole::Lieutenant->value,
            'joined_at' => now(),
        ]);

        $service = app(AccessService::class);

        $this->assertSame($membership->id, $service->squadronMembership($user, $squadron)?->id);
    }
}
