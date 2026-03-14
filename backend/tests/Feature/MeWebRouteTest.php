<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeWebRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_update_their_profile_via_the_web_me_route(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'rsi_handle' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/user/' . $user->id)
            ->put('/me', [
                'bio' => 'Ready to fly escort and logistics.',
                'timezone' => 'UTC',
                'availability_status' => 'available',
                'loa_note' => 'On most evenings.',
                'favorite_ships' => ['Avenger Titan'],
                'favorite_guns' => ['FS-9'],
                'callsign' => 'Ghost',
                'primary_role' => 'space_combat',
                'secondary_role' => 'medical',
                'preferred_gameplay_style' => 'casual/chill',
                'typical_op_commitment' => 'flexible',
                'experience_ratings' => [
                    'space_combat' => 4,
                    'ground_combat' => 3,
                    'logistics_support' => 5,
                    'medical' => 2,
                ],
            ]);

        $response
            ->assertRedirect('/user/' . $user->id)
            ->assertSessionHas('success', 'Profile updated.');

        $user->refresh();

        $this->assertSame('Ready to fly escort and logistics.', $user->bio);
        $this->assertSame('UTC', $user->timezone);
        $this->assertSame('available', $user->availability_status);
        $this->assertSame('On most evenings.', $user->loa_note);
        $this->assertSame(['Avenger Titan'], $user->favorite_ships);
        $this->assertSame(['FS-9'], $user->favorite_guns);
        $this->assertSame('Ghost', $user->callsign);
        $this->assertSame('space_combat', $user->primary_role);
        $this->assertSame('medical', $user->secondary_role);
        $this->assertSame('casual/chill', $user->preferred_gameplay_style);
        $this->assertSame('flexible', $user->typical_op_commitment);
        $this->assertSame([
            'space_combat' => 4,
            'ground_combat' => 3,
            'logistics_support' => 5,
            'medical' => 2,
        ], $user->experience_ratings);
    }
}
