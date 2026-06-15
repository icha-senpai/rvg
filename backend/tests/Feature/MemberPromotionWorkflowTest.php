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

    public function test_viewer_cannot_create_promotion_offer_for_themself(): void
    {
        $this->configurePromotionWorkflow();

        $viewer = $this->makeUserWithRank('admiral', [
            'rsi_handle' => 'FleetLead',
            'discord_id' => 'viewer-discord',
            'rsi_verified_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->from(route('member.profile', $viewer->rsi_handle))
            ->post(route('member.promotions.store', $viewer->rsi_handle), [
                'branch_role_id' => 'branch-cit-line',
            ])
            ->assertRedirect(route('member.profile', $viewer->rsi_handle))
            ->assertSessionHasErrors('promotion');

        $this->assertDatabaseCount('promotion_offers', 0);
    }

    public function test_invalid_branch_role_is_rejected_when_creating_promotion_offer(): void
    {
        $this->configurePromotionWorkflow();

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

        $this->actingAs($viewer)
            ->from(route('member.profile', $member->rsi_handle))
            ->post(route('member.promotions.store', $member->rsi_handle), [
                'branch_role_id' => 'not-a-real-branch',
            ])
            ->assertRedirect(route('member.profile', $member->rsi_handle))
            ->assertSessionHasErrors('promotion');

        $this->assertDatabaseCount('promotion_offers', 0);
    }

    public function test_authorized_viewer_can_cancel_pending_offer_from_profile(): void
    {
        $this->configurePromotionWorkflow();

        Http::fake(['*' => Http::response([], 200)]);

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

        $offer = PromotionOffer::create([
            'member_id' => $member->id,
            'promoter_id' => $viewer->id,
            'from_rank' => 'member',
            'to_rank' => 'cit',
            'discord_branch_role_id' => 'branch-cit-line',
            'state' => PromotionOffer::STATE_PENDING,
            'expires_at' => now()->addHours(4),
        ]);

        $this->actingAs($viewer)
            ->from(route('member.profile', $member->rsi_handle))
            ->post(route('member.promotions.cancel', [$member->rsi_handle, $offer->id]))
            ->assertRedirect(route('member.profile', $member->rsi_handle))
            ->assertSessionHas('success', 'Promotion offer cancelled.');

        $offer->refresh();

        $this->assertSame(PromotionOffer::STATE_CANCELLED, $offer->state);
        $this->assertNotNull($offer->cancelled_at);
        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $viewer->id,
            'action' => 'promotion_offer.cancelled',
        ]);
    }

    public function test_unauthorized_viewer_cannot_create_promotion_offer_from_profile(): void
    {
        $this->configurePromotionWorkflow();

        $viewer = $this->makeUserWithRank('cit', [
            'rsi_handle' => 'JuniorOfficer',
            'discord_id' => 'viewer-cit-discord',
            'rsi_verified_at' => now(),
        ]);

        $member = $this->makeUserWithRank('member', [
            'rsi_handle' => 'PilotZero',
            'discord_id' => 'member-discord',
            'rsi_verified_at' => now(),
        ]);

        $this
            ->actingAs($viewer)
            ->from(route('member.profile', $member->rsi_handle))
            ->post(route('member.promotions.store', $member->rsi_handle), [
                'branch_role_id' => 'branch-cit-line',
            ])
            ->assertRedirect(route('member.profile', $member->rsi_handle))
            ->assertSessionHasErrors('promotion');

        $this->assertDatabaseCount('promotion_offers', 0);
    }

    public function test_member_cannot_receive_second_pending_offer_while_one_is_active(): void
    {
        $this->configurePromotionWorkflow();
        Http::fake(['*' => Http::response([], 200)]);

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

        $offer = PromotionOffer::create([
            'member_id' => $member->id,
            'promoter_id' => $viewer->id,
            'from_rank' => 'member',
            'to_rank' => 'cit',
            'discord_branch_role_id' => 'branch-cit-line',
            'state' => PromotionOffer::STATE_PENDING,
            'expires_at' => now()->addHours(4),
        ]);

        $this->actingAs($viewer)
            ->from(route('member.profile', $member->rsi_handle))
            ->post(route('member.promotions.store', $member->rsi_handle), [
                'branch_role_id' => 'branch-cit-support',
            ])
            ->assertRedirect(route('member.profile', $member->rsi_handle))
            ->assertSessionHasErrors('promotion');

        $offer->refresh();

        $this->assertSame(PromotionOffer::STATE_PENDING, $offer->state);
        $this->assertDatabaseCount('promotion_offers', 1);
    }

    public function test_other_admiral_cannot_cancel_pending_offer_they_did_not_create(): void
    {
        $this->configurePromotionWorkflow();

        Http::fake(['*' => Http::response([], 200)]);

        $promoter = $this->makeUserWithRank('admiral', [
            'rsi_handle' => 'OriginalPromoter',
            'discord_id' => 'original-promoter-discord',
            'rsi_verified_at' => now(),
        ]);

        $otherAdmiral = $this->makeUserWithRank('admiral', [
            'rsi_handle' => 'SecondPromoter',
            'discord_id' => 'second-promoter-discord',
            'rsi_verified_at' => now(),
        ]);

        $member = $this->makeUserWithRank('member', [
            'rsi_handle' => 'PilotZero',
            'discord_id' => 'member-discord',
            'rsi_verified_at' => now(),
        ]);

        $offer = PromotionOffer::create([
            'member_id' => $member->id,
            'promoter_id' => $promoter->id,
            'from_rank' => 'member',
            'to_rank' => 'cit',
            'discord_branch_role_id' => 'branch-cit-line',
            'state' => PromotionOffer::STATE_PENDING,
            'expires_at' => now()->addHours(4),
        ]);

        $this->actingAs($otherAdmiral)
            ->from(route('member.profile', $member->rsi_handle))
            ->post(route('member.promotions.cancel', [$member->rsi_handle, $offer->id]))
            ->assertRedirect(route('member.profile', $member->rsi_handle))
            ->assertSessionHasErrors('promotion');

        $offer->refresh();

        $this->assertSame(PromotionOffer::STATE_PENDING, $offer->state);
        $this->assertNull($offer->cancelled_at);
    }

    public function test_cancel_route_returns_not_found_when_offer_does_not_belong_to_profile_member(): void
    {
        $this->configurePromotionWorkflow();

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

        $otherMember = $this->makeUserWithRank('member', [
            'rsi_handle' => 'PilotOther',
            'discord_id' => 'other-member-discord',
            'rsi_verified_at' => now(),
        ]);

        $offer = PromotionOffer::create([
            'member_id' => $member->id,
            'promoter_id' => $viewer->id,
            'from_rank' => 'member',
            'to_rank' => 'cit',
            'discord_branch_role_id' => 'branch-cit-line',
            'state' => PromotionOffer::STATE_PENDING,
            'expires_at' => now()->addHours(4),
        ]);

        $this->actingAs($viewer)
            ->post(route('member.promotions.cancel', [$otherMember->rsi_handle, $offer->id]))
            ->assertNotFound();

        $offer->refresh();

        $this->assertSame(PromotionOffer::STATE_PENDING, $offer->state);
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

    public function test_expired_offer_is_marked_expired_when_bot_tries_to_prepare_acceptance(): void
    {
        $this->configurePromotionWorkflow();
        Http::fake(['*' => Http::response([], 200)]);

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
            'discord_dm_message_id' => 'dm-expired-1',
            'state' => PromotionOffer::STATE_PENDING,
            'expires_at' => now()->subMinute(),
        ]);

        $headers = [
            'X-Bot-Secret' => 'test-bot-secret',
            'Accept' => 'application/json',
        ];

        $this->postJson('/api/v1/bot/promotions/messages/dm-expired-1/prepare-accept', [
            'discord_id' => 'member-discord',
            'message_id' => 'dm-expired-1',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'This promotion offer is no longer pending.');

        $offer->refresh();

        $this->assertSame(PromotionOffer::STATE_EXPIRED, $offer->state);
        $this->assertNotNull($offer->expired_at);
    }

    public function test_bot_prepare_acceptance_rejects_wrong_discord_member(): void
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
            'discord_dm_message_id' => 'dm-wrong-discord-1',
            'state' => PromotionOffer::STATE_PENDING,
            'expires_at' => now()->addHours(4),
        ]);

        $headers = [
            'X-Bot-Secret' => 'test-bot-secret',
            'Accept' => 'application/json',
        ];

        $this->postJson("/api/v1/bot/promotions/offers/{$offer->id}/prepare-accept", [
            'discord_id' => 'not-the-member',
            'message_id' => 'dm-wrong-discord-1',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Only the intended Discord member can accept this promotion.');
    }

    public function test_bot_prepare_acceptance_rejects_wrong_message_id_for_offer(): void
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
            'discord_dm_message_id' => 'dm-right-message-1',
            'state' => PromotionOffer::STATE_PENDING,
            'expires_at' => now()->addHours(4),
        ]);

        $headers = [
            'X-Bot-Secret' => 'test-bot-secret',
            'Accept' => 'application/json',
        ];

        $this->postJson("/api/v1/bot/promotions/offers/{$offer->id}/prepare-accept", [
            'discord_id' => 'member-discord',
            'message_id' => 'dm-wrong-message-1',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'This reaction does not match the tracked promotion DM.');
    }

    public function test_expired_pending_offer_is_replaced_by_a_new_offer_on_create(): void
    {
        $this->configurePromotionWorkflow();

        Http::fake([
            '*' => Http::response([
                'dm_message_id' => 'dm-999',
                'quarter_message_id' => 'quarter-999',
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

        $staleOffer = PromotionOffer::create([
            'member_id' => $member->id,
            'promoter_id' => $viewer->id,
            'from_rank' => 'member',
            'to_rank' => 'cit',
            'discord_branch_role_id' => 'branch-cit-line',
            'state' => PromotionOffer::STATE_PENDING,
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($viewer)
            ->from(route('member.profile', $member->rsi_handle))
            ->post(route('member.promotions.store', $member->rsi_handle), [
                'branch_role_id' => 'branch-cit-support',
            ])
            ->assertRedirect(route('member.profile', $member->rsi_handle))
            ->assertSessionHas('success', 'Promotion offer sent.');

        $staleOffer->refresh();

        $this->assertSame(PromotionOffer::STATE_EXPIRED, $staleOffer->state);
        $this->assertNotNull($staleOffer->expired_at);
        $this->assertDatabaseHas('promotion_offers', [
            'member_id' => $member->id,
            'promoter_id' => $viewer->id,
            'discord_branch_role_id' => 'branch-cit-support',
            'state' => PromotionOffer::STATE_PENDING,
            'discord_dm_message_id' => 'dm-999',
            'discord_quarter_message_id' => 'quarter-999',
        ]);
        $this->assertDatabaseCount('promotion_offers', 2);
    }

    public function test_accepted_offer_cannot_be_cancelled_from_profile(): void
    {
        $this->configurePromotionWorkflow();

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

        $offer = PromotionOffer::create([
            'member_id' => $member->id,
            'promoter_id' => $viewer->id,
            'from_rank' => 'member',
            'to_rank' => 'cit',
            'discord_branch_role_id' => 'branch-cit-line',
            'state' => PromotionOffer::STATE_ACCEPTED,
            'accepted_at' => now()->subMinute(),
            'expires_at' => now()->addHours(4),
        ]);

        $this->actingAs($viewer)
            ->from(route('member.profile', $member->rsi_handle))
            ->post(route('member.promotions.cancel', [$member->rsi_handle, $offer->id]))
            ->assertRedirect(route('member.profile', $member->rsi_handle))
            ->assertSessionHasErrors('promotion');

        $offer->refresh();

        $this->assertSame(PromotionOffer::STATE_ACCEPTED, $offer->state);
        $this->assertNull($offer->cancelled_at);
    }

    public function test_bot_finalize_acceptance_rejects_wrong_discord_member(): void
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
            'discord_dm_message_id' => 'dm-finalize-discord-1',
            'state' => PromotionOffer::STATE_PENDING,
            'expires_at' => now()->addHours(4),
        ]);

        $headers = [
            'X-Bot-Secret' => 'test-bot-secret',
            'Accept' => 'application/json',
        ];

        $this->postJson("/api/v1/bot/promotions/offers/{$offer->id}/finalize-accept", [
            'discord_id' => 'not-the-member',
            'message_id' => 'dm-finalize-discord-1',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Only the intended Discord member can finalize this promotion.');
    }

    public function test_bot_finalize_acceptance_rejects_wrong_message_id_for_offer(): void
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
            'discord_dm_message_id' => 'dm-finalize-right-1',
            'state' => PromotionOffer::STATE_PENDING,
            'expires_at' => now()->addHours(4),
        ]);

        $headers = [
            'X-Bot-Secret' => 'test-bot-secret',
            'Accept' => 'application/json',
        ];

        $this->postJson("/api/v1/bot/promotions/offers/{$offer->id}/finalize-accept", [
            'discord_id' => 'member-discord',
            'message_id' => 'dm-finalize-wrong-1',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'This finalize request does not match the tracked promotion DM.');
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

    public function test_demoting_member_cancels_pending_offer_and_resets_rank(): void
    {
        $this->configurePromotionWorkflow();
        Http::fake(['*' => Http::response([], 200)]);

        $viewer = $this->makeUserWithRank('director', [
            'rsi_handle' => 'DirectorPrime',
            'discord_id' => 'director-discord',
            'rsi_verified_at' => now(),
        ]);

        $member = $this->makeUserWithRank('cit', [
            'rsi_handle' => 'PilotZero',
            'discord_id' => 'member-discord',
            'rsi_verified_at' => now(),
        ]);

        $offer = PromotionOffer::create([
            'member_id' => $member->id,
            'promoter_id' => $viewer->id,
            'from_rank' => 'cit',
            'to_rank' => 'commander',
            'discord_branch_role_id' => 'branch-commander-line',
            'state' => PromotionOffer::STATE_PENDING,
            'expires_at' => now()->addHours(4),
        ]);

        $this->actingAs($viewer)
            ->from(route('member.profile', $member->rsi_handle))
            ->post(route('member.promotions.demote', $member->rsi_handle))
            ->assertRedirect(route('member.profile', $member->rsi_handle))
            ->assertSessionHas('success', 'Member demoted to Member.');

        $member->refresh()->load('roles');
        $offer->refresh();

        $this->assertSame('member', $member->rank);
        $this->assertFalse($member->roles->pluck('slug')->contains('cit'));
        $this->assertSame(PromotionOffer::STATE_CANCELLED, $offer->state);
        $this->assertSame($viewer->id, $offer->cancelled_by_user_id);
        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $viewer->id,
            'action' => 'promotion.demoted',
        ]);
        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $viewer->id,
            'action' => 'promotion_offer.cancelled',
        ]);
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
