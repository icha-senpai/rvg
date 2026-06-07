<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaSharedPropsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_verify_page_keeps_guest_auth_payload_empty(): void
    {
        $this->get('/verify')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify')
                ->where('auth.user', null)
                ->where('auth.can', [])
            );
    }

    public function test_authenticated_ledger_page_receives_shared_auth_payload(): void
    {
        config()->set('services.ledger.enabled', true);

        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
        ]);

        $memberRole = \App\Models\Role::query()->where('slug', 'member')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$memberRole->id]);

        $this->actingAs($user)
            ->get(route('ledger.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Ledger')
                ->where('auth.user.id', $user->id)
                ->where('features.ledger', true)
            );
    }
}
