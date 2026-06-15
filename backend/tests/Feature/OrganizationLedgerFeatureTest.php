<?php

namespace Tests\Feature;

use App\Models\LedgerAccount;
use App\Models\LedgerInventoryItem;
use App\Models\LedgerTransaction;
use App\Models\Operation;
use App\Models\OperationSettlement;
use App\Models\Role;
use App\Models\User;
use App\Models\WipeCycle;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationLedgerFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_org_ledger_manager_sees_only_org_owned_entries(): void
    {
        config()->set('services.ledger.enabled', true);

        $director = $this->directorUser();
        $member = $this->memberUser([
            'discord_id' => 'org-ledger-member',
            'discord_name' => 'Org Ledger Member',
            'rsi_handle' => 'OrgLedgerMember',
        ]);

        $wipe = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDays(5),
            'is_current' => true,
        ]);

        $personalAccount = LedgerAccount::query()->create([
            'user_id' => $member->id,
            'name' => 'Personal Ledger',
            'type' => 'personal',
            'currency' => 'aUEC',
            'is_default' => true,
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
            'user_id' => $member->id,
            'ledger_account_id' => $personalAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 7500,
            'currency' => 'aUEC',
            'source_type' => 'mission',
            'description' => 'Personal payout',
            'transaction_date' => now()->subHours(8),
        ]);

        LedgerTransaction::query()->create([
            'user_id' => $director->id,
            'ledger_account_id' => $orgAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'squadron_id' => null,
            'is_org_owned' => true,
            'type' => 'income',
            'amount' => 42000,
            'currency' => 'aUEC',
            'source_type' => 'treasury',
            'description' => 'Org reserve',
            'transaction_date' => now()->subHours(2),
        ]);

        $this
            ->actingAs($director)
            ->get(route('organization.ledger'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organization/Ledger')
                ->where('context.mode', 'organization')
                ->where('permissions.can_edit_ledger', true)
                ->where('permissions.can_manage_ledger', true)
                ->where('ledger.account.id', $orgAccount->id)
                ->where('ledger.account.name', 'Horizon Treasury')
                ->where('ledger.hasEntries', true)
                ->where('ledger.overview.cards.income', 42000)
                ->where('ledger.transactions.0.description', 'Org reserve')
                ->missing('ledger.transactions.1')
            );
    }

    public function test_regular_member_cannot_view_org_ledger(): void
    {
        config()->set('services.ledger.enabled', true);

        $member = $this->memberUser();

        $this
            ->actingAs($member)
            ->get(route('organization.ledger'))
            ->assertForbidden();
    }

    public function test_org_ledger_manager_can_create_update_and_delete_org_transaction(): void
    {
        config()->set('services.ledger.enabled', true);

        $director = $this->directorUser();

        $wipe = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDays(4),
            'is_current' => true,
        ]);

        $this
            ->actingAs($director)
            ->post(route('organization.ledger.transactions.store'), [
                'wipe_cycle_id' => $wipe->id,
                'type' => 'income',
                'amount' => 12000,
                'currency' => 'aUEC',
                'source_type' => 'treasury',
                'description' => 'Org seed fund',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $transaction = LedgerTransaction::query()
            ->where('description', 'Org seed fund')
            ->firstOrFail();

        $this->assertTrue((bool) $transaction->is_org_owned);
        $this->assertNull($transaction->squadron_id);
        $this->assertSame($director->id, $transaction->user_id);

        $this
            ->actingAs($director)
            ->put(route('organization.ledger.transactions.update', $transaction->id), [
                'ledger_account_id' => $transaction->ledger_account_id,
                'wipe_cycle_id' => $wipe->id,
                'type' => 'income',
                'amount' => 15000,
                'currency' => 'aUEC',
                'source_type' => 'treasury',
                'description' => 'Org reserve updated',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $transaction->refresh();

        $this->assertSame('Org reserve updated', $transaction->description);
        $this->assertSame('15000.00', $transaction->amount);
        $this->assertTrue((bool) $transaction->is_org_owned);

        $this
            ->actingAs($director)
            ->delete(route('organization.ledger.transactions.destroy', $transaction->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('ledger_transactions', [
            'id' => $transaction->id,
        ]);
    }

    public function test_org_settlement_receipts_stay_locked_in_org_ledger_flows(): void
    {
        config()->set('services.ledger.enabled', true);

        $director = $this->directorUser();
        $wipe = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDays(4),
            'is_current' => true,
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

        $operation = Operation::query()->create([
            'created_by' => $director->id,
            'title' => 'Org Settlement Lock Test',
            'description' => 'Completed operation for org ledger lock testing.',
            'status' => 'completed',
            'visibility' => 'open',
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
        ]);

        $settlement = OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
            'finalized_by_user_id' => $director->id,
            'finalized_at' => now()->subHour(),
        ]);

        $transaction = LedgerTransaction::query()->create([
            'user_id' => $director->id,
            'squadron_id' => null,
            'is_org_owned' => true,
            'ledger_account_id' => $orgAccount->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 42000,
            'currency' => 'aUEC',
            'source_type' => 'operation_settlement',
            'description' => 'Settlement org payout',
            'transaction_date' => now()->subHour(),
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'provenance_locked' => true,
        ]);

        $inventoryItem = LedgerInventoryItem::query()->create([
            'user_id' => $director->id,
            'squadron_id' => null,
            'is_org_owned' => true,
            'wipe_cycle_id' => $wipe->id,
            'related_operation_id' => $operation->id,
            'operation_settlement_id' => $settlement->id,
            'source_type' => 'component',
            'uex_reference_type' => 'item',
            'uex_reference_id' => 9101,
            'category' => 'Component',
            'quantity' => 2,
            'unit_label' => 'units',
            'currency' => 'aUEC',
            'status' => 'stored',
            'provenance_locked' => true,
            'acquired_at' => now()->subHour(),
        ]);

        $this
            ->actingAs($director)
            ->from('/organization/ledger')
            ->put(route('organization.ledger.transactions.update', $transaction), [
                'ledger_account_id' => $transaction->ledger_account_id,
                'wipe_cycle_id' => $wipe->id,
                'type' => 'income',
                'amount' => 45000,
                'currency' => 'aUEC',
                'source_type' => 'operation_settlement',
                'description' => 'Should stay locked',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('transaction');

        $this
            ->actingAs($director)
            ->from('/organization/ledger')
            ->delete(route('organization.ledger.transactions.destroy', $transaction))
            ->assertSessionHasErrors('transaction');

        $this
            ->actingAs($director)
            ->from('/organization/ledger')
            ->put(route('organization.ledger.inventory.update', $inventoryItem), [
                'wipe_cycle_id' => $wipe->id,
                'source_type' => 'component',
                'uex_reference_type' => 'item',
                'uex_reference_id' => 9101,
                'category' => 'Component',
                'quantity' => 4,
                'unit_label' => 'units',
                'currency' => 'aUEC',
                'status' => 'stored',
            ])
            ->assertSessionHasErrors('inventory');

        $this
            ->actingAs($director)
            ->from('/organization/ledger')
            ->delete(route('organization.ledger.inventory.destroy', $inventoryItem))
            ->assertSessionHasErrors('inventory');
    }

    protected function memberUser(array $overrides = []): User
    {
        $role = Role::query()->where('slug', 'member')->firstOrFail();

        $user = User::factory()->create(array_merge([
            'discord_id' => 'default-org-ledger-member',
            'discord_name' => 'Default Org Ledger Member',
            'rsi_handle' => 'DefaultOrgLedgerMember',
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
            'discord_id' => 'org-ledger-director',
            'discord_name' => 'Org Ledger Director',
            'rsi_handle' => 'OrgLedgerDirector',
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
