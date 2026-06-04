<?php

namespace Tests\Feature;

use App\Models\LedgerAccount;
use App\Models\LedgerTrade;
use App\Models\LedgerTransaction;
use App\Models\Role;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use App\Models\WipeCycle;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SquadronLedgerFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_active_squadron_member_sees_only_squadron_owned_entries(): void
    {
        config()->set('services.ledger.enabled', true);

        $viewer = $this->memberUser([
            'discord_id' => 'squadron-ledger-viewer',
            'discord_name' => 'Squadron Ledger Viewer',
            'rsi_handle' => 'SquadronLedgerViewer',
        ]);
        $teammate = $this->memberUser([
            'discord_id' => 'squadron-ledger-teammate',
            'discord_name' => 'Squadron Ledger Teammate',
            'rsi_handle' => 'SquadronLedgerTeammate',
        ]);
        $pending = $this->memberUser([
            'discord_id' => 'squadron-ledger-pending',
            'discord_name' => 'Squadron Ledger Pending',
            'rsi_handle' => 'SquadronLedgerPending',
        ]);

        $squadron = Squadron::query()->create([
            'name' => 'Ledger Wolves',
            'slug' => 'ledger-wolves',
            'status' => 'active',
            'branch' => 'defence',
            'division' => 'alpha',
            'leader_id' => $viewer->id,
            'recruiting' => true,
        ]);

        SquadronMember::query()->create([
            'user_id' => $viewer->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_LEADER,
            'joined_at' => now()->subDays(10),
        ]);

        SquadronMember::query()->create([
            'user_id' => $teammate->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_MEMBER,
            'joined_at' => now()->subDays(8),
        ]);

        SquadronMember::query()->create([
            'user_id' => $pending->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_PENDING,
            'role' => SquadronMember::ROLE_MEMBER,
        ]);

        $wipe = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDays(5),
            'is_current' => true,
        ]);

        $viewerAccount = LedgerAccount::query()->create([
            'user_id' => $viewer->id,
            'name' => 'Personal Ledger',
            'type' => 'personal',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);

        $teammateAccount = LedgerAccount::query()->create([
            'user_id' => $teammate->id,
            'name' => 'Personal Ledger',
            'type' => 'personal',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);

        $pendingAccount = LedgerAccount::query()->create([
            'user_id' => $pending->id,
            'name' => 'Personal Ledger',
            'type' => 'personal',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);

        $squadronAccount = LedgerAccount::query()->create([
            'user_id' => $viewer->id,
            'squadron_id' => $squadron->id,
            'name' => 'Squadron Treasury',
            'type' => 'squadron',
            'currency' => 'aUEC',
            'is_default' => false,
        ]);

        LedgerTransaction::query()->create([
            'user_id' => $viewer->id,
            'ledger_account_id' => $viewerAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 5000,
            'currency' => 'aUEC',
            'source_type' => 'mission',
            'description' => 'Viewer payout',
            'transaction_date' => now()->subDays(2),
        ]);

        LedgerTrade::query()->create([
            'user_id' => $teammate->id,
            'ledger_account_id' => $teammateAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'quantity' => 12,
            'unit_type' => 'SCU',
            'buy_price_per_unit' => 100,
            'sell_price_per_unit' => 150,
            'total_cost' => 1200,
            'total_revenue' => 1800,
            'profit' => 600,
            'profit_per_unit' => 50,
            'trade_date' => now()->subDay(),
        ]);

        LedgerTransaction::query()->create([
            'user_id' => $pending->id,
            'ledger_account_id' => $pendingAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 9000,
            'currency' => 'aUEC',
            'source_type' => 'mission',
            'description' => 'Pending payout',
            'transaction_date' => now()->subDay(),
        ]);

        LedgerTransaction::query()->create([
            'user_id' => $viewer->id,
            'squadron_id' => $squadron->id,
            'ledger_account_id' => $squadronAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 18000,
            'currency' => 'aUEC',
            'source_type' => 'treasury',
            'description' => 'Squadron funding',
            'transaction_date' => now()->subHours(10),
        ]);

        LedgerTrade::query()->create([
            'user_id' => $teammate->id,
            'squadron_id' => $squadron->id,
            'ledger_account_id' => $squadronAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'quantity' => 20,
            'unit_type' => 'SCU',
            'buy_price_per_unit' => 150,
            'sell_price_per_unit' => 200,
            'total_cost' => 3000,
            'total_revenue' => 4000,
            'profit' => 1000,
            'profit_per_unit' => 50,
            'trade_date' => now()->subHours(4),
        ]);

        $this
            ->actingAs($viewer)
            ->get(route('squadrons.ledger', ['squadron' => $squadron->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Squadrons/Ledger')
                ->where('squadron.name', 'Ledger Wolves')
                ->where('context.mode', 'squadron')
                ->where('permissions.can_edit_ledger', true)
                ->where('ledger.hasEntries', true)
                ->where('ledger.memberCount', 2)
                ->where('ledger.overview.cards.income', 19000)
                ->where('ledger.overview.cards.expenses', 3000)
                ->where('ledger.overview.cards.trade_profit', 1000)
                ->where('ledger.overview.cards.estimated_balance', 19000)
                ->where('ledger.overview.recentTransactions.0.description', 'Squadron funding')
                ->where('ledger.overview.recentTransactions.0.member_name', 'SquadronLedgerViewer')
                ->where('ledger.overview.recentTrades.0.member_name', 'SquadronLedgerTeammate')
                ->has('ledger.members', 2)
                ->where('permissions.can_manage_ledger', true)
            );
    }

    public function test_squadron_leader_can_create_update_and_delete_shared_records(): void
    {
        config()->set('services.ledger.enabled', true);

        $leader = $this->memberUser([
            'discord_id' => 'squadron-ledger-leader',
            'discord_name' => 'Squadron Ledger Leader',
            'rsi_handle' => 'SquadronLedgerLeader',
        ]);

        $squadron = Squadron::query()->create([
            'name' => 'Signal Foxes',
            'slug' => 'signal-foxes',
            'status' => 'active',
            'branch' => 'intel',
            'division' => 'gamma',
            'leader_id' => $leader->id,
            'recruiting' => true,
        ]);

        SquadronMember::query()->create([
            'user_id' => $leader->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_LEADER,
            'joined_at' => now()->subDays(6),
        ]);

        $wipe = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDays(4),
            'is_current' => true,
        ]);

        $this
            ->actingAs($leader)
            ->post(route('squadrons.ledger.transactions.store', ['squadron' => $squadron->id]), [
                'wipe_cycle_id' => $wipe->id,
                'type' => 'income',
                'amount' => 8500,
                'currency' => 'aUEC',
                'source_type' => 'treasury',
                'description' => 'Initial treasury',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $transaction = LedgerTransaction::query()->where('squadron_id', $squadron->id)->firstOrFail();

        $this->assertSame($leader->id, $transaction->user_id);
        $this->assertSame('Initial treasury', $transaction->description);

        $this
            ->actingAs($leader)
            ->put(route('squadrons.ledger.transactions.update', ['squadron' => $squadron->id, 'transaction' => $transaction->id]), [
                'ledger_account_id' => $transaction->ledger_account_id,
                'wipe_cycle_id' => $wipe->id,
                'type' => 'income',
                'amount' => 9100,
                'currency' => 'aUEC',
                'source_type' => 'treasury',
                'description' => 'Updated treasury',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $transaction->refresh();

        $this->assertSame('Updated treasury', $transaction->description);
        $this->assertSame('9100.00', $transaction->amount);

        $this
            ->actingAs($leader)
            ->delete(route('squadrons.ledger.transactions.destroy', ['squadron' => $squadron->id, 'transaction' => $transaction->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('ledger_transactions', [
            'id' => $transaction->id,
        ]);
    }

    public function test_regular_active_member_cannot_edit_shared_squadron_ledger(): void
    {
        config()->set('services.ledger.enabled', true);

        $leader = $this->memberUser([
            'discord_id' => 'squadron-ledger-leader-view',
            'discord_name' => 'Squadron Ledger Leader View',
            'rsi_handle' => 'SquadronLedgerLeaderView',
        ]);
        $member = $this->memberUser([
            'discord_id' => 'squadron-ledger-member-view',
            'discord_name' => 'Squadron Ledger Member View',
            'rsi_handle' => 'SquadronLedgerMemberView',
        ]);

        $squadron = Squadron::query()->create([
            'name' => 'Amber Echo',
            'slug' => 'amber-echo',
            'status' => 'active',
            'branch' => 'operations',
            'division' => 'delta',
            'leader_id' => $leader->id,
            'recruiting' => true,
        ]);

        SquadronMember::query()->create([
            'user_id' => $leader->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_LEADER,
            'joined_at' => now()->subDays(5),
        ]);

        SquadronMember::query()->create([
            'user_id' => $member->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_MEMBER,
            'joined_at' => now()->subDays(3),
        ]);

        $wipe = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDays(2),
            'is_current' => true,
        ]);

        $this
            ->actingAs($member)
            ->post(route('squadrons.ledger.transactions.store', ['squadron' => $squadron->id]), [
                'wipe_cycle_id' => $wipe->id,
                'type' => 'income',
                'amount' => 1000,
                'currency' => 'aUEC',
                'description' => 'Should fail',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertForbidden();
    }

    public function test_non_member_cannot_view_squadron_ledger(): void
    {
        config()->set('services.ledger.enabled', true);

        $member = $this->memberUser([
            'discord_id' => 'squadron-ledger-member',
            'discord_name' => 'Squadron Ledger Member',
            'rsi_handle' => 'SquadronLedgerMember',
        ]);
        $outsider = $this->memberUser([
            'discord_id' => 'squadron-ledger-outsider',
            'discord_name' => 'Squadron Ledger Outsider',
            'rsi_handle' => 'SquadronLedgerOutsider',
        ]);

        $squadron = Squadron::query()->create([
            'name' => 'Quiet Daggers',
            'slug' => 'quiet-daggers',
            'status' => 'active',
            'branch' => 'defence',
            'division' => 'beta',
            'leader_id' => $member->id,
            'recruiting' => true,
        ]);

        SquadronMember::query()->create([
            'user_id' => $member->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_LEADER,
            'joined_at' => now()->subDays(4),
        ]);

        $this
            ->actingAs($outsider)
            ->get(route('squadrons.ledger', ['squadron' => $squadron->slug]))
            ->assertForbidden();
    }

    protected function memberUser(array $overrides = []): User
    {
        $role = Role::query()->where('slug', 'member')->firstOrFail();

        $user = User::factory()->create(array_merge([
            'discord_id' => 'default-squadron-ledger-member',
            'discord_name' => 'Default Squadron Ledger Member',
            'rsi_handle' => 'DefaultSquadronLedgerMember',
            'rank' => 'member',
            'rank_level' => 1,
            'rsi_verified_at' => now(),
            'global_status' => User::STATUS_ACTIVE,
        ], $overrides));

        $user->roles()->attach($role->id);
        $user->load('roles');

        return $user;
    }
}
