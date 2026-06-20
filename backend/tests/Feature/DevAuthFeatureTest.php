<?php

namespace Tests\Feature;

use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevAuthFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_dev_auth_routes_are_hidden_when_feature_is_disabled(): void
    {
        config()->set('dev_auth.enabled', false);

        $this->get(route('dev.auth.index'))
            ->assertNotFound();
    }

    public function test_dev_auth_index_lists_personas_and_verify_shortcuts(): void
    {
        config()->set('dev_auth.enabled', true);

        $this->get(route('dev.auth.index'))
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('verify_urls.discord_step', route('verify'))
            ->assertJsonFragment([
                'slug' => 'penetrators_lt',
                'label' => 'Penetrators Lieutenant',
            ])
            ->assertJsonFragment([
                'slug' => 'verify_preview',
                'label' => 'Verify Preview',
            ]);
    }

    public function test_verified_persona_login_restores_intended_web_destination(): void
    {
        config()->set('dev_auth.enabled', true);

        $this->get(route('members.index'))
            ->assertRedirect(route('verify'));

        $this->get(route('dev.auth.login', ['persona' => 'member']))
            ->assertRedirect(route('members.index'));

        $this->assertAuthenticated();

        /** @var User $user */
        $user = auth()->user();

        $this->assertSame('dev-member', $user->discord_id);
        $this->assertTrue($user->hasRole('member'));
        $this->assertNotNull($user->rsi_verified_at);
    }

    public function test_penetrators_lieutenant_persona_gets_active_lieutenant_membership(): void
    {
        config()->set('dev_auth.enabled', true);

        $this->get(route('dev.auth.login', ['persona' => 'penetrators_lt']))
            ->assertRedirect('/');

        /** @var User $user */
        $user = auth()->user();
        $squadron = Squadron::query()->where('slug', 'penetrators')->firstOrFail();

        $membership = SquadronMember::query()
            ->where('user_id', $user->id)
            ->where('squadron_id', $squadron->id)
            ->first();

        $this->assertTrue($user->hasRole('lieutenant'));
        $this->assertNotNull($membership);
        $this->assertSame(SquadronMember::STATUS_ACTIVE, $membership->membership_status);
        $this->assertSame(SquadronMember::ROLE_LIEUTENANT, $membership->role);
    }

    public function test_verify_preview_persona_redirects_to_verify_as_an_unverified_user(): void
    {
        config()->set('dev_auth.enabled', true);

        $this->get(route('dev.auth.login', ['persona' => 'verify_preview']))
            ->assertRedirect(route('verify'));

        /** @var User $user */
        $user = auth()->user();

        $this->assertAuthenticated();
        $this->assertSame('dev-verify-preview', $user->discord_id);
        $this->assertNull($user->rsi_verified_at);
    }
}
