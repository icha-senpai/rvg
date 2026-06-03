<?php

namespace Tests\Feature;

use App\Models\PromotionOffer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberPromotionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_viewer_sees_profile_promotion_payload(): void
    {
        $this->configurePromotionWorkflow();

        $viewer = $this->makeUserWithRank('grand_admiral', [
            'rsi_handle' => 'HighCommand',
            'discord_id' => 'viewer-discord',
            'rsi_verified_at' => now(),
        ]);

        $member = $this->makeUserWithRank('member', [
            'rsi_handle' => 'RecruitOne',
            'discord_id' => 'member-discord',
            'rsi_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($viewer)
            ->get(route('member.profile', $member->rsi_handle));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Member/userpage')
            ->where('profileUser.promotion.show_panel', true)
            ->where('profileUser.promotion.next_rank.slug', 'cit')
            ->where('profileUser.promotion.can_create', true)
            ->has('profileUser.promotion.allowed_branch_roles', 2)
        );
    }

    public function test_authorized_viewer_can_create_promotion_offer_from_profile(): void
    {
        $this->configurePromotionWorkflow();

        Http::fake([
            '*' => Http::response([
                'dm_message_id' => 'dm-123',
                'quarter_message_id' => 'quarter-456',
            ], 200),
        ]);

        $viewer = $this->makeUserWithRank('admiral', [
            'rsi_handle' => 'FleetLead',
            'discord_id' => 'viewer-discord',
            'rsi_verified_at' => now(),
        ]);

        $member = $this->makeUserWithRank('member', [
            'rsi_handle' => 'PilotZero',
            'discord_id' => 'member-discord',
            'rsi_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($viewer)
            ->from(route('member.profile', $member->rsi_handle))
            ->post(route('member.promotions.store', $member->rsi_handle), [
                'branch_role_id' => 'branch-cit-line',
            ]);

        $response
            ->assertRedirect(route('member.profile', $member->rsi_handle))
            ->assertSessionHas('success', 'Promotion offer sent.');

        $this->assertDatabaseHas('promotion_offers', [
            'member_id' => $member->id,
            'promoter_id' => $viewer->id,
            'from_rank' => 'member',
            'to_rank' => 'cit',
            'discord_branch_role_id' => 'branch-cit-line',
            'state' => PromotionOffer::STATE_PENDING,
            'discord_dm_message_id' => 'dm-123',
            'discord_quarter_message_id' => 'quarter-456',
        ]);

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $viewer->id,
            'action' => 'promotion_offer.created',
        ]);
    }

    public function test_bot_prepare_and_finalize_acceptance_updates_rank_after_discord_success(): void
    {
        $this->configurePromotionWorkflow();

        $member = $this->makeUserWithRank('member', [
            'rsi_handle' => 'AceOne',
            'discord_id' => 'member-discord',
            'rsi_verified_at' => now(),
        ]);

        $promoter = $this->makeUserWithRank('grand_admiral', [
            'rsi_handle' => 'CommandPrime',
            'discord_id' => 'promoter-discord',
            'rsi_verified_at' => now(),
        ]);

        $offer = PromotionOffer::create([
            'member_id' => $member->id,
            'promoter_id' => $promoter->id,
            'from_rank' => 'member',
            'to_rank' => 'cit',
            'discord_branch_role_id' => 'branch-cit-line',
            'discord_dm_message_id' => 'dm-accept-1',
            'discord_quarter_message_id' => 'quarter-accept-1',
            'state' => PromotionOffer::STATE_PENDING,
            'expires_at' => now()->addHours(4),
        ]);

        $headers = [
            'X-Bot-Secret' => 'test-bot-secret',
            'Accept' => 'application/json',
        ];

        $prepareResponse = $this->postJson('/api/v1/bot/promotions/messages/dm-accept-1/prepare-accept', [
            'discord_id' => 'member-discord',
            'message_id' => 'dm-accept-1',
        ], $headers);

        $prepareResponse
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.offer_id', $offer->id)
            ->assertJsonPath('data.to_rank', 'cit')
            ->assertJsonPath('data.member_name', 'AceOne');

        $this->assertContains('rank-cit', $prepareResponse->json('data.add_role_ids'));
        $this->assertContains('branch-cit-line', $prepareResponse->json('data.add_role_ids'));

        $finalizeResponse = $this->postJson('/api/v1/bot/promotions/messages/dm-accept-1/finalize-accept', [
            'discord_id' => 'member-discord',
            'message_id' => 'dm-accept-1',
        ], $headers);

        $finalizeResponse
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.state', PromotionOffer::STATE_ACCEPTED)
            ->assertJsonPath('data.to_rank', 'cit');

        $member->refresh()->load('roles');
        $offer->refresh();

        $this->assertSame('cit', $member->rank);
        $this->assertSame(PromotionOffer::STATE_ACCEPTED, $offer->state);
        $this->assertNotNull($offer->accepted_at);
        $this->assertTrue($member->roles->pluck('slug')->contains('cit'));
        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $member->id,
            'action' => 'promotion_offer.accepted',
        ]);
    }

    public function test_admiral_promotion_is_wired_but_blocked_until_dm_template_exists(): void
    {
        $this->configurePromotionWorkflow();

        $viewer = $this->makeUserWithRank('director', [
            'rsi_handle' => 'DirectorPrime',
            'discord_id' => 'director-discord',
            'rsi_verified_at' => now(),
        ]);

        $member = $this->makeUserWithRank('wing_commander', [
            'rsi_handle' => 'WingLead',
            'discord_id' => 'wing-discord',
            'rsi_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($viewer)
            ->get(route('member.profile', $member->rsi_handle));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Member/userpage')
            ->where('profileUser.promotion.next_rank.slug', 'admiral')
            ->where('profileUser.promotion.can_create', false)
            ->where('profileUser.promotion.gating_reason', 'The DM template for Admiral is not configured yet.')
            ->has('profileUser.promotion.allowed_branch_roles', 1)
        );
    }

    public function test_grand_admiral_promotion_is_wired_but_blocked_until_dm_template_exists(): void
    {
        $this->configurePromotionWorkflow();

        $viewer = $this->makeUserWithRank('director', [
            'rsi_handle' => 'DirectorPrime',
            'discord_id' => 'director-discord',
            'rsi_verified_at' => now(),
        ]);

        $member = $this->makeUserWithRank('admiral', [
            'rsi_handle' => 'FlagLead',
            'discord_id' => 'flag-discord',
            'rsi_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($viewer)
            ->get(route('member.profile', $member->rsi_handle));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Member/userpage')
            ->where('profileUser.promotion.next_rank.slug', 'grand_admiral')
            ->where('profileUser.promotion.can_create', false)
            ->where('profileUser.promotion.gating_reason', 'The DM template for Grand Admiral is not configured yet.')
            ->has('profileUser.promotion.allowed_branch_roles', 1)
        );
    }

    protected function configurePromotionWorkflow(): void
    {
        Config::set('services.bot.secret', 'test-bot-secret');
        Config::set('services.discord.bot_secret', 'test-bot-secret');
        Config::set('services.bot.url', 'https://bot.example.test/bot');

        foreach (['member', 'cit', 'commander', 'wing_commander', 'admiral', 'grand_admiral', 'director'] as $slug) {
            $this->ensureRole($slug);
        }

        Config::set('promotions.offerable_targets', [
            'cit',
            'commander',
            'wing_commander',
            'admiral',
            'grand_admiral',
        ]);

        Config::set('promotions.discord.rank_role_ids', [
            'member' => 'rank-member',
            'cit' => 'rank-cit',
            'commander' => 'rank-commander',
            'wing_commander' => 'rank-wing-commander',
            'admiral' => 'rank-admiral',
            'grand_admiral' => 'rank-grand-admiral',
        ]);

        Config::set('promotions.discord.quarter_channel_ids', [
            'cit' => 'quarter-cit',
            'commander' => 'quarter-commander',
            'wing_commander' => 'quarter-wing-commander',
            'admiral' => 'quarter-admiral',
            'grand_admiral' => 'quarter-grand-admiral',
        ]);

        Config::set('promotions.discord.branch_roles', [
            'cit' => [
                ['id' => 'branch-cit-line', 'label' => 'Line Officer Branch'],
                ['id' => 'branch-cit-support', 'label' => 'Support Branch'],
            ],
            'commander' => [
                ['id' => 'branch-commander-line', 'label' => 'Line Officer Branch'],
            ],
            'wing_commander' => [
                ['id' => 'branch-wing-line', 'label' => 'Line Officer Branch'],
            ],
            'admiral' => [
                ['id' => 'branch-admiral-line', 'label' => 'Line Officer Branch'],
            ],
            'grand_admiral' => [
                ['id' => 'branch-grand-line', 'label' => 'Line Officer Branch'],
            ],
        ]);

        Config::set('promotions.quarter_templates', [
            'cit' => ':member_name pending :rank_label until :expires_at.',
            'commander' => ':member_name pending :rank_label until :expires_at.',
            'wing_commander' => ':member_name pending :rank_label until :expires_at.',
            'admiral' => ':member_name pending :rank_label until :expires_at.',
            'grand_admiral' => ':member_name pending :rank_label until :expires_at.',
        ]);
    }

    protected function makeUserWithRank(string $rank, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'global_status' => User::STATUS_ACTIVE,
            'rank' => $rank,
            'rank_level' => 1,
        ], $attributes));

        $memberRole = $this->ensureRole('member');
        $rankRole = $this->ensureRole($rank);

        $user->roles()->syncWithoutDetaching([$memberRole->id]);

        if ($rank !== 'member') {
            $user->roles()->syncWithoutDetaching([$rankRole->id]);
        }

        $user->setRank($rank);
        $user->load('roles');

        return $user;
    }

    protected function ensureRole(string $slug): Role
    {
        return Role::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => str($slug)->replace('_', ' ')->title()->toString(),
                'description' => 'Test role ' . $slug,
                'is_system' => true,
            ]
        );
    }
}
