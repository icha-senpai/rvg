<?php

namespace Tests\Feature;

use App\Models\LedgerAccount;
use App\Models\LedgerInventoryItem;
use App\Models\LedgerShipAsset;
use App\Models\LedgerTrade;
use App\Models\LedgerTransferRequest;
use App\Models\LedgerTransaction;
use App\Models\Role;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use App\Models\WipeCycle;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LedgerFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_member_can_open_personal_ledger_when_feature_is_enabled(): void
    {
        config()->set('services.ledger.enabled', true);

        $member = $this->memberUser();

        $this
            ->actingAs($member)
            ->get(route('ledger.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Ledger')
                ->where('ledger.account.name', 'Personal Ledger')
                ->where('ledger.activeWipeFilter', 'current')
            );
    }

    public function test_feature_disabled_blocks_regular_member_ledger_access(): void
    {
        config()->set('services.ledger.enabled', false);

        $member = $this->memberUser();

        $this
            ->actingAs($member)
            ->get(route('ledger.index'))
            ->assertForbidden();
    }

    public function test_preview_user_can_access_ledger_while_feature_is_disabled(): void
    {
        $member = $this->memberUser();

        config()->set('services.ledger.enabled', false);
        config()->set('services.ledger.preview_user_ids', [$member->id]);

        $this
            ->actingAs($member)
            ->get(route('ledger.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Ledger')
                ->where('ledger.account.name', 'Personal Ledger')
            );
    }

    public function test_shared_pending_transfer_badges_are_exposed_for_personal_squadron_and_org_ledgers(): void
    {
        config()->set('services.ledger.enabled', true);

        $director = $this->directorUser();
        $otherMember = $this->memberUser([
            'discord_id' => 'badge-target-member',
            'discord_name' => 'Badge Target Member',
            'rsi_handle' => 'BadgeTargetMember',
        ]);

        $squadron = Squadron::query()->create([
            'name' => 'Badge Watch',
            'slug' => 'badge-watch',
            'status' => 'active',
            'branch' => 'operations',
            'division' => 'alpha',
            'leader_id' => $director->id,
            'recruiting' => true,
        ]);

        SquadronMember::query()->create([
            'user_id' => $director->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_LEADER,
            'joined_at' => now()->subDays(5),
        ]);

        LedgerTransferRequest::query()->create([
            'transfer_kind' => 'funds',
            'status' => 'pending',
            'requested_by_user_id' => $otherMember->id,
            'source_user_id' => $otherMember->id,
            'destination_user_id' => $director->id,
            'currency' => 'aUEC',
            'amount' => 1500,
            'description' => 'Personal approval needed',
        ]);

        LedgerTransferRequest::query()->create([
            'transfer_kind' => 'inventory',
            'status' => 'pending',
            'requested_by_user_id' => $director->id,
            'source_user_id' => $director->id,
            'destination_user_id' => $otherMember->id,
            'currency' => 'aUEC',
            'quantity' => 2,
            'description' => 'Personal outbound pending',
        ]);

        LedgerTransferRequest::query()->create([
            'transfer_kind' => 'funds',
            'status' => 'pending',
            'requested_by_user_id' => $otherMember->id,
            'source_user_id' => $otherMember->id,
            'destination_squadron_id' => $squadron->id,
            'currency' => 'aUEC',
            'amount' => 4200,
            'description' => 'Squadron approval needed',
        ]);

        LedgerTransferRequest::query()->create([
            'transfer_kind' => 'inventory',
            'status' => 'pending',
            'requested_by_user_id' => $director->id,
            'source_user_id' => $director->id,
            'destination_is_org_owned' => true,
            'currency' => 'aUEC',
            'quantity' => 1,
            'description' => 'Org approval needed',
        ]);

        $this
            ->actingAs($director)
            ->get(route('ledger.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Ledger')
                ->where('pendingTransferBadges.personal', 1)
                ->where('pendingTransferBadges.squadron', 1)
                ->where('pendingTransferBadges.organization', 1)
            );
    }

    public function test_shared_pending_squadron_applications_count_is_exposed_for_managers(): void
    {
        config()->set('services.ledger.enabled', true);

        $director = $this->directorUser();
        $applicant = $this->memberUser([
            'discord_id' => 'squad-applicant',
            'discord_name' => 'Squad Applicant',
            'rsi_handle' => 'SquadApplicant',
        ]);

        $squadron = Squadron::query()->create([
            'name' => 'Application Watch',
            'slug' => 'application-watch',
            'status' => 'active',
            'branch' => 'operations',
            'division' => 'alpha',
            'leader_id' => $director->id,
            'recruiting' => true,
        ]);

        SquadronMember::query()->create([
            'user_id' => $director->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_LEADER,
            'joined_at' => now()->subDays(5),
        ]);

        SquadronMember::query()->create([
            'user_id' => $applicant->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_PENDING,
            'role' => SquadronMember::ROLE_MEMBER,
        ]);

        $this
            ->actingAs($director)
            ->get(route('ledger.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Ledger')
                ->where('pendingSquadronApplications', 1)
            );
    }

    public function test_personal_ledger_excludes_squadron_owned_entries_even_when_the_same_member_created_them(): void
    {
        config()->set('services.ledger.enabled', true);

        $member = $this->memberUser();

        $squadron = Squadron::query()->create([
            'name' => 'Ledger Owls',
            'slug' => 'ledger-owls',
            'status' => 'active',
            'branch' => 'operations',
            'division' => 'alpha',
            'leader_id' => $member->id,
            'recruiting' => true,
        ]);

        SquadronMember::query()->create([
            'user_id' => $member->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_LEADER,
            'joined_at' => now()->subDays(7),
        ]);

        $wipe = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDays(3),
            'is_current' => true,
        ]);

        $personalAccount = LedgerAccount::query()->create([
            'user_id' => $member->id,
            'name' => 'Personal Ledger',
            'type' => 'personal',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);

        $squadronAccount = LedgerAccount::query()->create([
            'user_id' => $member->id,
            'squadron_id' => $squadron->id,
            'name' => 'Squadron Treasury',
            'type' => 'squadron',
            'currency' => 'aUEC',
            'is_default' => false,
        ]);

        LedgerTransaction::query()->create([
            'user_id' => $member->id,
            'ledger_account_id' => $personalAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 4000,
            'currency' => 'aUEC',
            'source_type' => 'mission',
            'description' => 'Personal payout',
            'transaction_date' => now()->subHours(8),
        ]);

        LedgerTransaction::query()->create([
            'user_id' => $member->id,
            'squadron_id' => $squadron->id,
            'ledger_account_id' => $squadronAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 12000,
            'currency' => 'aUEC',
            'source_type' => 'treasury',
            'description' => 'Squadron funding',
            'transaction_date' => now()->subHours(2),
        ]);

        $this
            ->actingAs($member)
            ->get(route('ledger.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Ledger')
                ->where('ledger.overview.cards.income', 4000)
                ->where('ledger.overview.cards.estimated_balance', 4000)
                ->where('ledger.overview.recentTransactions.0.description', 'Personal payout')
            );
    }

    public function test_creating_a_transaction_defaults_to_the_current_wipe_and_default_account(): void
    {
        config()->set('services.ledger.enabled', true);

        $member = $this->memberUser();

        $this
            ->actingAs($member)
            ->post(route('ledger.transactions.store'), [
                'type' => 'income',
                'amount' => 42500.75,
                'source_type' => 'salvage',
                'description' => 'Reclaimer split payout',
            ])
            ->assertRedirect();

        $currentWipe = WipeCycle::query()->where('is_current', true)->firstOrFail();
        $account = LedgerAccount::query()->where('user_id', $member->id)->where('is_default', true)->firstOrFail();

        $this->assertDatabaseHas('ledger_transactions', [
            'user_id' => $member->id,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $currentWipe->id,
            'type' => 'income',
            'source_type' => 'salvage',
            'description' => 'Reclaimer split payout',
        ]);

        $this->assertDatabaseHas('ledger_activity_logs', [
            'actor_user_id' => $member->id,
            'subject_user_id' => $member->id,
            'action' => 'transaction.created',
            'target_type' => 'LedgerTransaction',
        ]);
    }

    public function test_member_cannot_create_a_new_transaction_in_an_archived_cycle(): void
    {
        config()->set('services.ledger.enabled', true);

        $member = $this->memberUser();
        $archivedWipe = WipeCycle::query()->create([
            'name' => '4.0 Live',
            'star_citizen_version' => '4.0',
            'wipe_type' => 'full',
            'started_at' => now()->subMonths(2),
            'ended_at' => now()->subMonth(),
            'is_current' => false,
        ]);

        $this
            ->actingAs($member)
            ->from(route('ledger.index', ['wipe' => $archivedWipe->id]))
            ->post(route('ledger.transactions.store'), [
                'wipe_cycle_id' => $archivedWipe->id,
                'type' => 'income',
                'amount' => 9000,
                'source_type' => 'mission',
                'description' => 'Archived payout',
            ])
            ->assertRedirect(route('ledger.index', ['wipe' => $archivedWipe->id]))
            ->assertSessionHasErrors('wipe_cycle_id');

        $this->assertDatabaseMissing('ledger_transactions', [
            'user_id' => $member->id,
            'description' => 'Archived payout',
        ]);
    }

    public function test_member_cannot_update_an_archived_transaction(): void
    {
        config()->set('services.ledger.enabled', true);

        $member = $this->memberUser();
        $archivedWipe = WipeCycle::query()->create([
            'name' => '4.0 Live',
            'star_citizen_version' => '4.0',
            'wipe_type' => 'full',
            'started_at' => now()->subMonths(2),
            'ended_at' => now()->subMonth(),
            'is_current' => false,
        ]);
        $account = LedgerAccount::query()->create([
            'user_id' => $member->id,
            'name' => 'Personal Ledger',
            'type' => 'personal',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);
        $transaction = LedgerTransaction::query()->create([
            'user_id' => $member->id,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $archivedWipe->id,
            'type' => 'income',
            'amount' => 4200,
            'currency' => 'aUEC',
            'source_type' => 'mission',
            'description' => 'Archived record',
            'transaction_date' => now()->subMonths(2),
        ]);

        $this
            ->actingAs($member)
            ->from(route('ledger.index', ['wipe' => $archivedWipe->id]))
            ->put(route('ledger.transactions.update', $transaction), [
                'ledger_account_id' => $account->id,
                'wipe_cycle_id' => $archivedWipe->id,
                'type' => 'expense',
                'amount' => 1200,
                'currency' => 'aUEC',
                'source_type' => 'repair',
                'description' => 'Should not update',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect(route('ledger.index', ['wipe' => $archivedWipe->id]))
            ->assertSessionHasErrors('wipe_cycle_id');

        $transaction->refresh();

        $this->assertSame('Archived record', $transaction->description);
        $this->assertSame('income', $transaction->type);
    }

    public function test_member_can_update_their_own_transaction(): void
    {
        config()->set('services.ledger.enabled', true);

        $member = $this->memberUser();
        $wipe = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDays(2),
            'is_current' => true,
        ]);
        $account = LedgerAccount::query()->create([
            'user_id' => $member->id,
            'name' => 'Personal Ledger',
            'type' => 'personal',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);
        $transaction = LedgerTransaction::query()->create([
            'user_id' => $member->id,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 5000,
            'currency' => 'aUEC',
            'source_type' => 'salvage',
            'description' => 'Original payout',
            'transaction_date' => now()->subDay(),
        ]);

        $this
            ->actingAs($member)
            ->put(route('ledger.transactions.update', $transaction), [
                'ledger_account_id' => $account->id,
                'wipe_cycle_id' => $wipe->id,
                'type' => 'expense',
                'amount' => 1450.5,
                'currency' => 'aUEC',
                'source_type' => 'repair',
                'description' => 'Updated repair bill',
                'transaction_date' => now()->toDateTimeString(),
                'notes' => 'Hull patch and restock',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ledger_transactions', [
            'id' => $transaction->id,
            'user_id' => $member->id,
            'type' => 'expense',
            'amount' => 1450.50,
            'source_type' => 'repair',
            'description' => 'Updated repair bill',
            'notes' => 'Hull patch and restock',
        ]);

        $this->assertDatabaseHas('ledger_activity_logs', [
            'actor_user_id' => $member->id,
            'subject_user_id' => $member->id,
            'action' => 'transaction.updated',
            'target_type' => 'LedgerTransaction',
            'target_id' => $transaction->id,
        ]);
    }

    public function test_member_can_delete_their_own_transaction(): void
    {
        config()->set('services.ledger.enabled', true);

        $member = $this->memberUser();
        $wipe = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDays(2),
            'is_current' => true,
        ]);
        $account = LedgerAccount::query()->create([
            'user_id' => $member->id,
            'name' => 'Personal Ledger',
            'type' => 'personal',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);
        $transaction = LedgerTransaction::query()->create([
            'user_id' => $member->id,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 2200,
            'currency' => 'aUEC',
            'source_type' => 'mission',
            'description' => 'Delete me',
            'transaction_date' => now(),
        ]);

        $this
            ->actingAs($member)
            ->delete(route('ledger.transactions.destroy', $transaction))
            ->assertRedirect();

        $this->assertDatabaseMissing('ledger_transactions', [
            'id' => $transaction->id,
        ]);

        $this->assertDatabaseHas('ledger_activity_logs', [
            'actor_user_id' => $member->id,
            'subject_user_id' => $member->id,
            'action' => 'transaction.deleted',
            'target_type' => 'LedgerTransaction',
            'target_id' => $transaction->id,
        ]);
    }

    public function test_view_only_user_cannot_create_ledger_transactions(): void
    {
        config()->set('services.ledger.enabled', true);

        $viewer = $this->viewerUser();

        $this
            ->actingAs($viewer)
            ->post(route('ledger.transactions.store'), [
                'type' => 'income',
                'amount' => 1250,
                'source_type' => 'mission',
                'description' => 'Should be blocked',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('ledger_transactions', [
            'user_id' => $viewer->id,
            'description' => 'Should be blocked',
        ]);
    }

    public function test_member_cannot_update_someone_elses_transaction(): void
    {
        config()->set('services.ledger.enabled', true);

        $member = $this->memberUser();
        $otherMember = $this->memberUser([
            'discord_id' => 'ledger-other-member',
            'discord_name' => 'Ledger Other Member',
            'rsi_handle' => 'LedgerOtherMember',
        ]);
        $wipe = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDays(2),
            'is_current' => true,
        ]);
        $account = LedgerAccount::query()->create([
            'user_id' => $otherMember->id,
            'name' => 'Personal Ledger',
            'type' => 'personal',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);
        $transaction = LedgerTransaction::query()->create([
            'user_id' => $otherMember->id,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 6500,
            'currency' => 'aUEC',
            'source_type' => 'mining',
            'description' => 'Protected transaction',
            'transaction_date' => now(),
        ]);

        $this
            ->actingAs($member)
            ->put(route('ledger.transactions.update', $transaction), [
                'ledger_account_id' => $account->id,
                'wipe_cycle_id' => $wipe->id,
                'type' => 'expense',
                'amount' => 100,
                'currency' => 'aUEC',
                'source_type' => 'repair',
                'description' => 'Attempted hijack',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('ledger_transactions', [
            'id' => $transaction->id,
            'description' => 'Protected transaction',
            'amount' => 6500.00,
        ]);
    }

    public function test_selected_wipe_filters_ledger_reports_and_overview_cards(): void
    {
        config()->set('services.ledger.enabled', true);

        $member = $this->memberUser();
        $account = LedgerAccount::query()->create([
            'user_id' => $member->id,
            'name' => 'Personal Ledger',
            'type' => 'personal',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);

        $archivedWipe = WipeCycle::query()->create([
            'name' => '4.0 Live',
            'star_citizen_version' => '4.0',
            'wipe_type' => 'partial',
            'started_at' => now()->subDays(20),
            'ended_at' => now()->subDays(7),
            'is_current' => false,
        ]);

        $currentWipe = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDays(6),
            'is_current' => true,
        ]);

        LedgerTransaction::query()->create([
            'user_id' => $member->id,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $archivedWipe->id,
            'type' => 'income',
            'amount' => 1500,
            'currency' => 'aUEC',
            'source_type' => 'trade',
            'description' => 'Archived wipe income',
            'transaction_date' => now()->subDays(10),
        ]);

        LedgerTrade::query()->create([
            'user_id' => $member->id,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $archivedWipe->id,
            'quantity' => 10,
            'unit_type' => 'SCU',
            'buy_price_per_unit' => 100,
            'sell_price_per_unit' => 120,
            'total_cost' => 1000,
            'total_revenue' => 1200,
            'profit' => 200,
            'profit_per_unit' => 20,
            'trade_date' => now()->subDays(9),
        ]);

        LedgerTransaction::query()->create([
            'user_id' => $member->id,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $currentWipe->id,
            'type' => 'income',
            'amount' => 9200,
            'currency' => 'aUEC',
            'source_type' => 'mission',
            'description' => 'Current wipe income',
            'transaction_date' => now()->subDays(2),
        ]);

        $this
            ->actingAs($member)
            ->get(route('ledger.index', ['wipe' => $archivedWipe->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Ledger')
                ->where('ledger.activeWipeFilter', (string) $archivedWipe->id)
                ->where('ledger.selectedWipe.id', $archivedWipe->id)
                ->where('ledger.overview.cards.income', 1700)
                ->where('ledger.overview.cards.expenses', 1000)
                ->where('ledger.overview.cards.trade_profit', 200)
                ->where('ledger.overview.cards.estimated_balance', 1700)
                ->has('ledger.overview.cycleSummaries', 2)
                ->where('ledger.overview.cycleComparison', null)
                ->has('ledger.reports.profitLossByWipe', 1)
                ->where('ledger.reports.profitLossByWipe.0.label', '4.0 Live')
                ->where('ledger.reports.profitLossByWipe.0.value', 1700)
            );
    }

    public function test_overview_includes_current_vs_previous_cycle_comparison(): void
    {
        config()->set('services.ledger.enabled', true);

        $member = $this->memberUser([
            'discord_id' => 'ledger-comparison-member',
            'discord_name' => 'Ledger Comparison Member',
            'rsi_handle' => 'LedgerComparisonMember',
        ]);
        $account = LedgerAccount::query()->create([
            'user_id' => $member->id,
            'name' => 'Personal Ledger',
            'type' => 'personal',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);

        $previousCycle = WipeCycle::query()->create([
            'name' => '4.0 Live',
            'star_citizen_version' => '4.0',
            'wipe_type' => 'partial',
            'started_at' => now()->subDays(18),
            'ended_at' => now()->subDays(8),
            'is_current' => false,
        ]);

        $currentCycle = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDays(7),
            'is_current' => true,
        ]);

        LedgerTransaction::query()->create([
            'user_id' => $member->id,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $previousCycle->id,
            'type' => 'income',
            'amount' => 1000,
            'currency' => 'aUEC',
            'source_type' => 'mission',
            'description' => 'Previous income',
            'transaction_date' => now()->subDays(12),
        ]);

        LedgerTrade::query()->create([
            'user_id' => $member->id,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $previousCycle->id,
            'quantity' => 5,
            'unit_type' => 'SCU',
            'buy_price_per_unit' => 100,
            'sell_price_per_unit' => 120,
            'total_cost' => 500,
            'total_revenue' => 600,
            'profit' => 100,
            'profit_per_unit' => 20,
            'trade_date' => now()->subDays(11),
        ]);

        LedgerTransaction::query()->create([
            'user_id' => $member->id,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $currentCycle->id,
            'type' => 'income',
            'amount' => 2100,
            'currency' => 'aUEC',
            'source_type' => 'trade',
            'description' => 'Current income',
            'transaction_date' => now()->subDays(3),
        ]);

        LedgerTrade::query()->create([
            'user_id' => $member->id,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $currentCycle->id,
            'quantity' => 8,
            'unit_type' => 'SCU',
            'buy_price_per_unit' => 120,
            'sell_price_per_unit' => 170,
            'total_cost' => 960,
            'total_revenue' => 1360,
            'profit' => 400,
            'profit_per_unit' => 50,
            'trade_date' => now()->subDays(2),
        ]);

        $this
            ->actingAs($member)
            ->get(route('ledger.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Ledger')
                ->where('ledger.overview.cycleComparison.focus.name', '4.1 Live')
                ->where('ledger.overview.cycleComparison.baseline.name', '4.0 Live')
                ->where('ledger.overview.cycleComparison.metrics.0.label', 'Net Position')
                ->where('ledger.overview.cycleComparison.metrics.0.focus_value', 2500)
                ->where('ledger.overview.cycleComparison.metrics.0.baseline_value', 1100)
                ->where('ledger.overview.cycleComparison.metrics.0.delta', 1400)
                ->where('ledger.overview.cycleComparison.metrics.1.label', 'Trade Profit')
                ->where('ledger.overview.cycleComparison.metrics.1.focus_value', 400)
                ->where('ledger.overview.cycleComparison.metrics.1.baseline_value', 100)
                ->where('ledger.overview.cycleComparison.metrics.1.delta', 300)
                ->has('ledger.overview.cycleSummaries', 2)
                ->where('ledger.overview.cycleSummaries.0.name', '4.1 Live')
                ->where('ledger.overview.cycleSummaries.1.name', '4.0 Live')
            );
    }

    public function test_ledger_reference_payload_includes_uex_trade_inventory_and_ship_suggestions(): void
    {
        config()->set('services.ledger.enabled', true);

        $member = $this->memberUser();
        $timestamp = now();
        $wipe = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDay(),
            'is_current' => true,
        ]);
        $account = LedgerAccount::query()->create([
            'user_id' => $member->id,
            'name' => 'Personal Ledger',
            'type' => 'personal',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);

        DB::table('uex_commodities')->insert([
            'uex_id' => 101,
            'name' => 'Agricium',
            'source_payload' => json_encode(['name' => 'Agricium']),
            'last_synced_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table('uex_items')->insert([
            'uex_id' => 202,
            'name' => 'Atlas Drive',
            'type' => 'Quantum Drive',
            'source_payload' => json_encode(['name' => 'Atlas Drive']),
            'last_synced_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table('uex_vehicles')->insert([
            'uex_id' => 303,
            'name' => 'C2',
            'full_name' => 'Hercules Starlifter C2',
            'type' => 'Ship',
            'source_payload' => json_encode([
                'name' => 'C2',
                'url_photo' => 'https://cdn.uex.test/ships/c2.jpg',
            ]),
            'last_synced_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table('uex_commodity_prices')->insert([
            [
                'commodity_uex_id' => 101,
                'terminal_uex_id' => 1,
                'terminal_name' => 'Area18 TDD',
                'price_buy' => 15.25,
                'price_sell' => 18.75,
                'source_payload' => json_encode(['terminal_name' => 'Area18 TDD']),
                'last_synced_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'commodity_uex_id' => 101,
                'terminal_uex_id' => 2,
                'terminal_name' => 'Everus Harbor',
                'price_buy' => 14.75,
                'price_sell' => 19.5,
                'source_payload' => json_encode(['terminal_name' => 'Everus Harbor']),
                'last_synced_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);

        DB::table('uex_item_prices')->insert([
            [
                'item_uex_id' => 202,
                'terminal_uex_id' => 10,
                'terminal_name' => 'Cousin Crows',
                'price_buy' => 5400,
                'price_sell' => 6200,
                'source_payload' => json_encode(['terminal_name' => 'Cousin Crows']),
                'last_synced_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'item_uex_id' => 202,
                'terminal_uex_id' => 11,
                'terminal_name' => 'CenterMass',
                'price_buy' => 5250,
                'price_sell' => 6100,
                'source_payload' => json_encode(['terminal_name' => 'CenterMass']),
                'last_synced_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);

        DB::table('uex_vehicle_purchase_prices')->insert([
            [
                'vehicle_uex_id' => 303,
                'terminal_uex_id' => 20,
                'terminal_name' => 'New Deal',
                'price_buy' => 4900000,
                'source_payload' => json_encode(['terminal_name' => 'New Deal']),
                'last_synced_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'vehicle_uex_id' => 303,
                'terminal_uex_id' => 21,
                'terminal_name' => 'Astro Armada',
                'price_buy' => 5050000,
                'source_payload' => json_encode(['terminal_name' => 'Astro Armada']),
                'last_synced_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);

        DB::table('uex_vehicle_rental_prices')->insert([
            [
                'vehicle_uex_id' => 303,
                'terminal_uex_id' => 30,
                'terminal_name' => 'Traveler Rentals',
                'price_rent' => 245000,
                'source_payload' => json_encode(['terminal_name' => 'Traveler Rentals']),
                'last_synced_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);

        LedgerTrade::query()->create([
            'user_id' => $member->id,
            'ledger_account_id' => $account->id,
            'wipe_cycle_id' => $wipe->id,
            'commodity_uex_id' => 101,
            'quantity' => 10,
            'unit_type' => 'SCU',
            'buy_price_per_unit' => 14.75,
            'sell_price_per_unit' => 19.50,
            'total_cost' => 147.50,
            'total_revenue' => 195.00,
            'profit' => 47.50,
            'profit_per_unit' => 4.75,
            'trade_date' => now()->subHours(2),
        ]);

        LedgerInventoryItem::query()->create([
            'user_id' => $member->id,
            'wipe_cycle_id' => $wipe->id,
            'source_type' => 'commodity',
            'uex_reference_type' => 'commodity',
            'uex_reference_id' => 101,
            'quantity' => 2,
            'unit_label' => 'SCU',
            'purchase_price' => 29.50,
            'estimated_value' => 39.00,
            'currency' => 'aUEC',
            'status' => 'owned',
            'acquired_at' => now()->subHours(3),
        ]);

        LedgerInventoryItem::query()->create([
            'user_id' => $member->id,
            'wipe_cycle_id' => $wipe->id,
            'source_type' => 'item',
            'uex_reference_type' => 'item',
            'uex_reference_id' => 202,
            'quantity' => 1,
            'unit_label' => 'units',
            'purchase_price' => 5250,
            'estimated_value' => 6200,
            'currency' => 'aUEC',
            'status' => 'owned',
            'acquired_at' => now()->subHours(1),
        ]);

        LedgerShipAsset::query()->create([
            'user_id' => $member->id,
            'wipe_cycle_id' => $wipe->id,
            'vehicle_uex_id' => 303,
            'purchase_price' => 4900000,
            'currency' => 'aUEC',
            'status' => 'owned',
            'acquired_at' => now()->subMinutes(30),
        ]);

        $this
            ->actingAs($member)
            ->get(route('ledger.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Ledger')
                ->has('ledger.references.tradePricing', 1)
                ->where('ledger.references.tradePricing.0.uex_id', 101)
                ->where('ledger.references.tradePricing.0.buy_price_per_unit', 14.75)
                ->where('ledger.references.tradePricing.0.buy_terminal_name', 'Everus Harbor')
                ->where('ledger.references.tradePricing.0.sell_price_per_unit', 19.5)
                ->where('ledger.references.tradePricing.0.sell_terminal_name', 'Everus Harbor')
                ->has('ledger.references.inventoryValuations.commodities', 1)
                ->where('ledger.references.inventoryValuations.commodities.0.unit_purchase_price', 14.75)
                ->where('ledger.references.inventoryValuations.commodities.0.unit_estimated_value', 19.5)
                ->has('ledger.references.inventoryValuations.items', 1)
                ->where('ledger.references.inventoryValuations.items.0.category', 'Quantum Drive')
                ->where('ledger.references.inventoryValuations.items.0.unit_purchase_price', 5250)
                ->where('ledger.references.inventoryValuations.items.0.purchase_terminal_name', 'CenterMass')
                ->where('ledger.references.inventoryValuations.items.0.unit_estimated_value', 6200)
                ->where('ledger.references.inventoryValuations.items.0.estimated_terminal_name', 'Cousin Crows')
                ->where('ledger.references.ships.0.image_url', 'https://cdn.uex.test/ships/c2.jpg')
                ->has('ledger.references.shipPricing', 1)
                ->where('ledger.references.shipPricing.0.purchase_price', 4900000)
                ->where('ledger.references.shipPricing.0.purchase_terminal_name', 'New Deal')
                ->where('ledger.references.shipPricing.0.rental_price', 245000)
                ->where('ledger.references.shipPricing.0.rental_terminal_name', 'Traveler Rentals')
                ->where('ledger.overview.cards.trade_spend', 147.5)
                ->where('ledger.overview.cards.commodity_inventory_value', 39)
                ->where('ledger.overview.cards.gear_inventory_value', 6200)
                ->where('ledger.overview.cards.fleet_value', 4900000)
                ->where('ledger.overview.cards.inventory_estimate_count', 2)
                ->where('ledger.overview.cards.ship_estimate_count', 1)
                ->where('ledger.overview.topCommodityTrades.0.label', 'Agricium')
                ->where('ledger.overview.topCommodityTrades.0.value', 47.5)
                ->where('ledger.inventoryItems.0.estimated_value_source', 'uex_estimate')
                ->where('ledger.inventoryItems.0.purchase_price_source', 'uex_estimate')
                ->where('ledger.shipAssets.0.purchase_price_source', 'uex_estimate')
            );
    }

    public function test_admin_can_rotate_the_current_wipe_cycle(): void
    {
        config()->set('services.ledger.enabled', true);

        $director = $this->directorUser();

        $existing = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'partial',
            'started_at' => now()->subDays(7),
            'is_current' => true,
        ]);

        $this
            ->actingAs($director)
            ->post(route('admin.ledger.wipes.store'), [
                'name' => '4.1.1 Live',
                'star_citizen_version' => '4.1.1',
                'wipe_type' => 'inventory',
                'started_at' => now()->toDateTimeString(),
                'notes' => 'Inventory reset after patch.',
            ])
            ->assertRedirect();

        $existing->refresh();
        $replacement = WipeCycle::query()->where('name', '4.1.1 Live')->firstOrFail();

        $this->assertFalse($existing->is_current);
        $this->assertNotNull($existing->ended_at);
        $this->assertTrue($replacement->is_current);

        $this
            ->actingAs($director)
            ->post(route('admin.ledger.wipes.activate', $existing))
            ->assertRedirect();

        $existing->refresh();
        $replacement->refresh();

        $this->assertTrue($existing->is_current);
        $this->assertFalse($replacement->is_current);
    }

    public function test_admin_can_rename_the_current_cycle_but_not_archived_cycles(): void
    {
        config()->set('services.ledger.enabled', true);

        $director = $this->directorUser();
        $updatedStartedAt = now()->subHours(6)->startOfMinute();

        $archived = WipeCycle::query()->create([
            'name' => '4.0 Live',
            'star_citizen_version' => '4.0',
            'wipe_type' => 'partial',
            'started_at' => now()->subDays(10),
            'ended_at' => now()->subDays(2),
            'is_current' => false,
        ]);

        $current = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDay(),
            'is_current' => true,
        ]);

        $this
            ->actingAs($director)
            ->post(route('admin.ledger.wipes.rename', $current), [
                'name' => '4.1.1 Live',
                'star_citizen_version' => '4.1.1',
                'wipe_type' => 'economy',
                'started_at' => $updatedStartedAt->toDateTimeString(),
            ])
            ->assertRedirect();

        $current->refresh();
        $this->assertSame('4.1.1 Live', $current->name);
        $this->assertSame('4.1.1', $current->star_citizen_version);
        $this->assertSame('economy', $current->wipe_type);
        $this->assertTrue($current->started_at?->equalTo($updatedStartedAt));

        $this->assertDatabaseHas('ledger_activity_logs', [
            'actor_user_id' => $director->id,
            'action' => 'wipe_cycle.updated',
            'target_type' => 'WipeCycle',
            'target_id' => $current->id,
        ]);

        $this
            ->actingAs($director)
            ->from(route('admin.dashboard'))
            ->post(route('admin.ledger.wipes.rename', $archived), [
                'name' => 'Renamed Archive',
                'star_citizen_version' => '4.0.1',
                'wipe_type' => 'full',
                'started_at' => now()->subDays(9)->toDateTimeString(),
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors('name');

        $archived->refresh();
        $this->assertSame('4.0 Live', $archived->name);
        $this->assertSame('4.0', $archived->star_citizen_version);
        $this->assertSame('partial', $archived->wipe_type);
    }

    protected function memberUser(array $overrides = []): User
    {
        $role = Role::query()->where('slug', 'member')->firstOrFail();

        $user = User::factory()->create(array_merge([
            'discord_id' => 'ledger-member',
            'discord_name' => 'Ledger Member',
            'rsi_handle' => 'LedgerMember',
            'rank' => 'member',
            'rank_level' => 1,
            'rsi_verified_at' => now(),
            'global_status' => User::STATUS_ACTIVE,
        ], $overrides));

        $user->roles()->attach($role->id);
        $user->load('roles');

        return $user;
    }

    protected function directorUser(): User
    {
        $role = Role::query()->where('slug', 'director')->firstOrFail();

        $user = User::factory()->create([
            'discord_id' => 'ledger-director',
            'discord_name' => 'Ledger Director',
            'rsi_handle' => 'LedgerDirector',
            'rank' => 'director',
            'rank_level' => 8,
            'rsi_verified_at' => now(),
            'global_status' => User::STATUS_ACTIVE,
        ]);

        $user->roles()->attach($role->id);
        $user->load('roles');

        return $user;
    }

    protected function viewerUser(): User
    {
        $role = Role::query()->where('slug', 'viewer')->firstOrFail();

        $user = User::factory()->create([
            'discord_id' => 'ledger-viewer',
            'discord_name' => 'Ledger Viewer',
            'rsi_handle' => 'LedgerViewer',
            'rank' => 'viewer',
            'rank_level' => 0,
            'rsi_verified_at' => now(),
            'global_status' => User::STATUS_ACTIVE,
        ]);

        $user->roles()->attach($role->id);
        $user->load('roles');

        return $user;
    }
}
