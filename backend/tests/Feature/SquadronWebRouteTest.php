<?php

namespace Tests\Feature;

use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SquadronWebRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_apply_to_a_squadron_via_the_web_route(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
        ]);

        $squadron = Squadron::create([
            'name' => 'Void Rangers',
            'slug' => 'void-rangers',
            'status' => 'active',
            'recruiting' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/squadrons?squadron=' . $squadron->slug)
            ->post('/squadrons/' . $squadron->id . '/join');

        $response
            ->assertRedirect('/squadrons?squadron=' . $squadron->slug)
            ->assertSessionHas('success', 'Applied to squadron successfully.');

        $this->assertDatabaseHas('squadron_members', [
            'user_id' => $user->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_PENDING,
        ]);
    }
}
