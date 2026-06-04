<?php

namespace Tests\Feature;

use App\Models\LedgerActivityLog;
use App\Models\LedgerInventoryItem;
use App\Models\LedgerTransaction;
use App\Models\Role;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use App\Models\WipeCycle;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTransferFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_personal_transfer_to_squadron_creates_balanced_records(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->directorUser();
        $squadron = $this->managedSquadronFor($actor, 'Atlas Finance');
        $wipe = $this->currentWipe();

        $this
            ->actingAs($actor)
            ->post(route('ledger.transactions.transfer'), [
                'wipe_cycle_id' => $wipe->id,
                'destination_type' => 'squadron',
                'destination_squadron_id' => $squadron->id,
                'amount' => 12500,
                'description' => 'Fuel reserve',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ledger_transactions', [
            'user_id' => $actor->id,
            'squadron_id' => null,
            'is_org_owned' => false,
            'type' => 'expense',
            'amount' => '12500.00',
            'source_type' => 'transfer',
            'description' => 'Transfer to Atlas Finance Squadron Ledger: Fuel reserve',
        ]);

        $this->assertDatabaseHas('ledger_transactions', [
            'user_id' => $actor->id,
            'squadron_id' => $squadron->id,
            'is_org_owned' => false,
            'type' => 'income',
            'amount' => '12500.00',
            'source_type' => 'transfer',
            'description' => 'Transfer from Personal Ledger: Fuel reserve',
        ]);

        $this->assertSame(2, LedgerActivityLog::query()->where('action', 'transfer.created')->count());
    }

    public function test_squadron_transfer_to_org_creates_balanced_records(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->directorUser();
        $squadron = $this->managedSquadronFor($actor, 'Signal Accountants');
        $wipe = $this->currentWipe();

        $this
            ->actingAs($actor)
            ->post(route('squadrons.ledger.transactions.transfer', ['squadron' => $squadron->id]), [
                'wipe_cycle_id' => $wipe->id,
                'destination_type' => 'organization',
                'amount' => 48000,
                'description' => 'Ops tax sweep',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ledger_transactions', [
            'user_id' => $actor->id,
            'squadron_id' => $squadron->id,
            'is_org_owned' => false,
            'type' => 'expense',
            'amount' => '48000.00',
            'source_type' => 'transfer',
            'description' => 'Transfer to Org Treasury: Ops tax sweep',
        ]);

        $this->assertDatabaseHas('ledger_transactions', [
            'user_id' => $actor->id,
            'squadron_id' => null,
            'is_org_owned' => true,
            'type' => 'income',
            'amount' => '48000.00',
            'source_type' => 'transfer',
            'description' => 'Transfer from Signal Accountants Squadron Ledger: Ops tax sweep',
        ]);
    }

    public function test_transfer_records_cannot_be_edited_or_deleted_like_manual_transactions(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->directorUser();
        $wipe = $this->currentWipe();

        $this
            ->actingAs($actor)
            ->post(route('ledger.transactions.transfer'), [
                'wipe_cycle_id' => $wipe->id,
                'destination_type' => 'organization',
                'amount' => 9000,
                'description' => 'Treasury top-up',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $transaction = LedgerTransaction::query()
            ->where('user_id', $actor->id)
            ->whereNull('squadron_id')
            ->where('is_org_owned', false)
            ->where('source_type', 'transfer')
            ->firstOrFail();

        $this
            ->actingAs($actor)
            ->put(route('ledger.transactions.update', $transaction->id), [
                'ledger_account_id' => $transaction->ledger_account_id,
                'wipe_cycle_id' => $wipe->id,
                'type' => 'expense',
                'amount' => 9100,
                'currency' => 'aUEC',
                'source_type' => 'transfer',
                'description' => 'Edited transfer',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('transaction');

        $this
            ->actingAs($actor)
            ->delete(route('ledger.transactions.destroy', $transaction->id))
            ->assertSessionHasErrors('transaction');

        $this->assertDatabaseHas('ledger_transactions', [
            'id' => $transaction->id,
        ]);
    }

    public function test_partial_inventory_transfer_to_squadron_splits_the_record(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->directorUser();
        $squadron = $this->managedSquadronFor($actor, 'Cargo Foxes');
        $wipe = $this->currentWipe();

        $item = LedgerInventoryItem::query()->create([
            'user_id' => $actor->id,
            'wipe_cycle_id' => $wipe->id,
            'source_type' => 'commodity',
            'uex_reference_type' => 'commodity',
            'uex_reference_id' => 101,
            'custom_name' => 'Agricium',
            'quantity' => 20,
            'unit_label' => 'SCU',
            'purchase_price' => 20000,
            'estimated_value' => 26000,
            'currency' => 'aUEC',
            'status' => 'owned',
            'acquired_at' => now()->subDay(),
        ]);

        $this
            ->actingAs($actor)
            ->post(route('ledger.inventory.transfer'), [
                'inventory_item_id' => $item->id,
                'destination_type' => 'squadron',
                'destination_squadron_id' => $squadron->id,
                'quantity' => 5,
                'notes' => 'Moved to squad cargo pool',
            ])
            ->assertRedirect();

        $item->refresh();

        $this->assertSame('15.0000', $item->quantity);
        $this->assertSame('15000.00', $item->purchase_price);
        $this->assertSame('19500.00', $item->estimated_value);

        $this->assertDatabaseHas('ledger_inventory_items', [
            'squadron_id' => $squadron->id,
            'is_org_owned' => false,
            'custom_name' => 'Agricium',
            'quantity' => '5.0000',
            'purchase_price' => '5000.00',
            'estimated_value' => '6500.00',
        ]);

        $this->assertSame(2, LedgerActivityLog::query()->whereIn('action', ['inventory.transfer_out', 'inventory.transfer_in'])->count());
    }

    public function test_full_inventory_transfer_to_org_reassigns_the_record(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->directorUser();
        $wipe = $this->currentWipe();

        $item = LedgerInventoryItem::query()->create([
            'user_id' => $actor->id,
            'wipe_cycle_id' => $wipe->id,
            'source_type' => 'item',
            'uex_reference_type' => 'item',
            'uex_reference_id' => 202,
            'custom_name' => 'Atlas Drive',
            'quantity' => 2,
            'unit_label' => 'units',
            'purchase_price' => 8000,
            'estimated_value' => 9000,
            'currency' => 'aUEC',
            'status' => 'owned',
            'acquired_at' => now()->subDay(),
        ]);

        $this
            ->actingAs($actor)
            ->post(route('ledger.inventory.transfer'), [
                'inventory_item_id' => $item->id,
                'destination_type' => 'organization',
                'quantity' => 2,
                'notes' => 'Moved into treasury stock',
            ])
            ->assertRedirect();

        $item->refresh();

        $this->assertTrue((bool) $item->is_org_owned);
        $this->assertNull($item->squadron_id);
        $this->assertSame($actor->id, $item->user_id);
        $this->assertStringContainsString('Moved into treasury stock', (string) $item->notes);
    }

    public function test_archived_inventory_cannot_be_transferred(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->directorUser();
        $squadron = $this->managedSquadronFor($actor, 'Archive Lockers');
        $archivedWipe = WipeCycle::query()->create([
            'name' => '4.0 Live',
            'star_citizen_version' => '4.0',
            'wipe_type' => 'full',
            'started_at' => now()->subMonths(2),
            'ended_at' => now()->subMonth(),
            'is_current' => false,
        ]);

        $item = LedgerInventoryItem::query()->create([
            'user_id' => $actor->id,
            'wipe_cycle_id' => $archivedWipe->id,
            'source_type' => 'commodity',
            'uex_reference_type' => 'commodity',
            'uex_reference_id' => 404,
            'custom_name' => 'Gold',
            'quantity' => 10,
            'unit_label' => 'SCU',
            'purchase_price' => 45000,
            'estimated_value' => 51000,
            'currency' => 'aUEC',
            'status' => 'owned',
            'acquired_at' => now()->subMonths(2),
        ]);

        $this
            ->actingAs($actor)
            ->post(route('ledger.inventory.transfer'), [
                'inventory_item_id' => $item->id,
                'destination_type' => 'squadron',
                'destination_squadron_id' => $squadron->id,
                'quantity' => 1,
                'notes' => 'Should not move',
            ])
            ->assertSessionHasErrors('wipe_cycle_id');

        $item->refresh();

        $this->assertSame('10.0000', $item->quantity);
        $this->assertNull($item->squadron_id);
        $this->assertFalse((bool) $item->is_org_owned);
    }

    protected function managedSquadronFor(User $actor, string $name): Squadron
    {
        $squadron = Squadron::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'status' => 'active',
            'branch' => 'finance',
            'division' => 'hq',
            'leader_id' => $actor->id,
            'recruiting' => true,
        ]);

        SquadronMember::query()->create([
            'user_id' => $actor->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_LEADER,
            'joined_at' => now()->subDays(10),
        ]);

        return $squadron;
    }

    protected function currentWipe(): WipeCycle
    {
        return WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDays(4),
            'is_current' => true,
        ]);
    }

    protected function directorUser(): User
    {
        $role = Role::query()->where('slug', 'director')->firstOrFail();

        $user = User::factory()->create([
            'discord_id' => 'ledger-transfer-director',
            'discord_name' => 'Ledger Transfer Director',
            'rsi_handle' => 'LedgerTransferDirector',
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
