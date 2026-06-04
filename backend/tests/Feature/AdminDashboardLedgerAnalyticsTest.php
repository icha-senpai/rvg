<?php

namespace Tests\Feature;

use App\Models\LedgerAccount;
use App\Models\LedgerInventoryItem;
use App\Models\LedgerShipAsset;
use App\Models\LedgerTrade;
use App\Models\LedgerTransaction;
use App\Models\Role;
use App\Models\Squadron;
use App\Models\User;
use App\Models\WipeCycle;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardLedgerAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_admin_dashboard_includes_leadership_ledger_analytics(): void
    {
        $director = $this->directorUser();

        $wipe = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDays(4),
            'is_current' => true,
        ]);

        $squadron = Squadron::query()->create([
            'name' => 'Atlas',
            'slug' => 'atlas',
            'status' => 'active',
        ]);

        $personalAccount = LedgerAccount::query()->create([
            'user_id' => $director->id,
            'squadron_id' => null,
            'is_org_owned' => false,
            'name' => 'Personal Ledger',
            'type' => 'personal',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);

        $squadronAccount = LedgerAccount::query()->create([
            'user_id' => $director->id,
            'squadron_id' => $squadron->id,
            'is_org_owned' => false,
            'name' => 'Atlas Treasury',
            'type' => 'squadron',
            'currency' => 'aUEC',
            'is_default' => false,
        ]);

        $orgAccount = LedgerAccount::query()->create([
            'user_id' => $director->id,
            'squadron_id' => null,
            'is_org_owned' => true,
            'name' => 'Horizon Treasury',
            'type' => 'organization',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);

        LedgerTransaction::query()->create([
            'user_id' => $director->id,
            'ledger_account_id' => $personalAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 10000,
            'currency' => 'aUEC',
            'source_type' => 'mission',
            'description' => 'Personal payout',
            'transaction_date' => now()->subHours(6),
        ]);

        LedgerTransaction::query()->create([
            'user_id' => $director->id,
            'ledger_account_id' => $orgAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'squadron_id' => null,
            'is_org_owned' => true,
            'type' => 'income',
            'amount' => 8000,
            'currency' => 'aUEC',
            'source_type' => 'treasury',
            'description' => 'Org reserve',
            'transaction_date' => now()->subHours(5),
        ]);

        LedgerTrade::query()->create([
            'user_id' => $director->id,
            'ledger_account_id' => $squadronAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'squadron_id' => $squadron->id,
            'is_org_owned' => false,
            'commodity_uex_id' => null,
            'quantity' => 50,
            'unit_type' => 'SCU',
            'buy_price_per_unit' => 100,
            'sell_price_per_unit' => 150,
            'total_cost' => 5000,
            'total_revenue' => 7500,
            'profit' => 2500,
            'profit_per_unit' => 50,
            'trade_date' => now()->subHours(4),
        ]);

        LedgerInventoryItem::query()->create([
            'user_id' => $director->id,
            'ledger_account_id' => $orgAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'squadron_id' => null,
            'is_org_owned' => true,
            'source_type' => 'item',
            'category' => 'components',
            'name' => 'Shield Generator Stack',
            'quantity' => 8,
            'purchase_price' => 32000,
            'estimated_value' => 32000,
            'currency' => 'aUEC',
            'acquired_at' => now()->subHours(3),
        ]);

        LedgerShipAsset::query()->create([
            'user_id' => $director->id,
            'wipe_cycle_id' => $wipe->id,
            'squadron_id' => null,
            'is_org_owned' => true,
            'status' => 'owned',
            'custom_name' => 'Command Hull',
            'purchase_price' => 500000,
            'currency' => 'aUEC',
            'acquired_at' => now()->subHours(2),
        ]);

        $this
            ->actingAs($director)
            ->get(route('admin.dashboard', ['tab' => 'ledger']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('ledger.analytics.snapshot.net_position', 20500)
                ->where('ledger.analytics.snapshot.income', 20500)
                ->where('ledger.analytics.snapshot.expenses', 5000)
                ->where('ledger.analytics.snapshot.trade_profit', 2500)
                ->where('ledger.analytics.snapshot.inventory_value', 32000)
                ->where('ledger.analytics.snapshot.fleet_value', 500000)
                ->has('ledger.analytics.ownership', 3)
                ->where('ledger.analytics.ownership.0.label', 'Personal Ledgers')
                ->where('ledger.analytics.ownership.1.label', 'Squadron Ledgers')
                ->where('ledger.analytics.ownership.2.label', 'Org Treasury')
                ->where('ledger.analytics.reports.income_by_source.0.label', 'mission')
                ->where('ledger.analytics.reports.trade_profit_by_commodity.0.label', 'Unknown Commodity')
            );
    }

    protected function directorUser(): User
    {
        $role = Role::query()->where('slug', 'director')->firstOrFail();

        $user = User::factory()->create([
            'discord_id' => 'admin-ledger-director',
            'discord_name' => 'Admin Ledger Director',
            'rsi_handle' => 'AdminLedgerDirector',
            'rank' => 'director',
            'rank_level' => 8,
            'rsi_verified_at' => now(),
            'global_status' => User::STATUS_ACTIVE,
        ]);

        $user->roles()->attach($role->id);
        $user->load('roles');

        return $user;
    }
}
