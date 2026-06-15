<?php

namespace Tests\Feature;

use App\Models\LedgerActivityLog;
use App\Models\LedgerInventoryItem;
use App\Models\LedgerTransferRequest;
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

    public function test_personal_transfer_to_squadron_creates_pending_request(): void
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

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->assertSame('pending', $request->status);
        $this->assertSame($squadron->id, $request->destination_squadron_id);
        $this->assertDatabaseMissing('ledger_transactions', [
            'transfer_request_id' => $request->id,
        ]);
        $this->assertSame(2, LedgerActivityLog::query()->where('action', 'transfer.requested')->count());
    }

    public function test_personal_transfer_to_verified_member_creates_pending_request_until_recipient_approves(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('SenderHandle', 'sender-discord');
        $recipient = $this->verifiedUser('ReceiverHandle', 'receiver-discord');
        $wipe = $this->currentWipe();

        $this
            ->actingAs($actor)
            ->post(route('ledger.transactions.transfer'), [
                'wipe_cycle_id' => $wipe->id,
                'destination_type' => 'personal',
                'destination_user_id' => $recipient->id,
                'amount' => 6400,
                'description' => 'Reimbursement',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->assertSame('pending', $request->status);
        $this->assertSame($actor->id, $request->requested_by_user_id);
        $this->assertSame($recipient->id, $request->destination_user_id);
        $this->assertDatabaseMissing('ledger_transactions', [
            'transfer_request_id' => $request->id,
        ]);
    }

    public function test_verified_member_personal_transfer_to_their_active_squadron_stays_pending_until_approved(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('QuarterFox', 'quarter-fox');
        $squadron = $this->memberSquadronFor($actor, 'Quartermasters');
        $wipe = $this->currentWipe();

        $this
            ->actingAs($actor)
            ->post(route('ledger.transactions.transfer'), [
                'wipe_cycle_id' => $wipe->id,
                'destination_type' => 'squadron',
                'destination_squadron_id' => $squadron->id,
                'amount' => 7200,
                'description' => 'Shared pool top-up',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->assertSame('pending', $request->status);
        $this->assertSame($squadron->id, $request->destination_squadron_id);
        $this->assertDatabaseMissing('ledger_transactions', [
            'transfer_request_id' => $request->id,
        ]);
    }

    public function test_verified_member_transfer_to_another_active_squadron_creates_pending_request(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('CrossCargo', 'cross-cargo');
        $recipientSquadron = $this->unmanagedSquadron('Far Traders');
        $wipe = $this->currentWipe();

        $this
            ->actingAs($actor)
            ->post(route('ledger.transactions.transfer'), [
                'wipe_cycle_id' => $wipe->id,
                'destination_type' => 'squadron',
                'destination_squadron_id' => $recipientSquadron->id,
                'amount' => 5100,
                'description' => 'Shared resource drop',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->assertSame('pending', $request->status);
        $this->assertSame($recipientSquadron->id, $request->destination_squadron_id);
        $this->assertDatabaseMissing('ledger_transactions', [
            'transfer_request_id' => $request->id,
        ]);
    }

    public function test_verified_member_personal_transfer_to_horizon_treasury_creates_pending_request(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('TreasuryDonor', 'treasury-donor');
        $wipe = $this->currentWipe();

        $this
            ->actingAs($actor)
            ->post(route('ledger.transactions.transfer'), [
                'wipe_cycle_id' => $wipe->id,
                'destination_type' => 'organization',
                'amount' => 8800,
                'description' => 'Treasury donation',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->assertSame('pending', $request->status);
        $this->assertTrue((bool) $request->destination_is_org_owned);
        $this->assertDatabaseMissing('ledger_transactions', [
            'transfer_request_id' => $request->id,
        ]);
    }

    public function test_squadron_transfer_to_org_creates_pending_request(): void
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

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->assertSame('pending', $request->status);
        $this->assertTrue((bool) $request->destination_is_org_owned);
        $this->assertDatabaseMissing('ledger_transactions', [
            'transfer_request_id' => $request->id,
        ]);
    }

    public function test_regular_squadron_leader_can_transfer_to_org_without_org_permission(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('SquadBanker', 'squad-banker');
        $squadron = $this->managedSquadronFor($actor, 'Signal Accountants');
        $wipe = $this->currentWipe();

        $this
            ->actingAs($actor)
            ->post(route('squadrons.ledger.transactions.transfer', ['squadron' => $squadron->id]), [
                'wipe_cycle_id' => $wipe->id,
                'destination_type' => 'organization',
                'amount' => 18000,
                'description' => 'Shared tax sweep',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->assertSame('pending', $request->status);
        $this->assertTrue((bool) $request->destination_is_org_owned);
        $this->assertDatabaseMissing('ledger_transactions', [
            'transfer_request_id' => $request->id,
        ]);
    }

    public function test_org_manager_transfer_to_a_verified_member_personal_records_creates_pending_request(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->directorUser();
        $recipient = $this->verifiedUser('ReturnPilot', 'return-pilot');
        $wipe = $this->currentWipe();

        $this
            ->actingAs($actor)
            ->post(route('organization.ledger.transactions.transfer'), [
                'wipe_cycle_id' => $wipe->id,
                'destination_type' => 'personal',
                'destination_user_id' => $recipient->id,
                'amount' => 15000,
                'description' => 'Mission reimbursement',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->assertSame('pending', $request->status);
        $this->assertSame($recipient->id, $request->destination_user_id);
        $this->assertDatabaseMissing('ledger_transactions', [
            'transfer_request_id' => $request->id,
        ]);
    }

    public function test_director_level_squadron_transfer_to_another_active_squadron_creates_pending_request(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->directorUser();
        $sourceSquadron = $this->managedSquadronFor($actor, 'Atlas Finance');
        $recipientSquadron = $this->unmanagedSquadron('Prospectors Union');
        $wipe = $this->currentWipe();

        $this
            ->actingAs($actor)
            ->post(route('squadrons.ledger.transactions.transfer', ['squadron' => $sourceSquadron->id]), [
                'wipe_cycle_id' => $wipe->id,
                'destination_type' => 'squadron',
                'destination_squadron_id' => $recipientSquadron->id,
                'amount' => 9400,
                'description' => 'Cross-squadron reserve',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->assertSame('pending', $request->status);
        $this->assertSame($recipientSquadron->id, $request->destination_squadron_id);
        $this->assertDatabaseMissing('ledger_transactions', [
            'transfer_request_id' => $request->id,
        ]);
    }

    public function test_org_manager_can_approve_pending_fund_transfer_into_horizon_treasury(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('TreasuryDonor', 'treasury-donor-approve');
        $approver = $this->directorUser();
        $wipe = $this->currentWipe();

        $this
            ->actingAs($actor)
            ->post(route('ledger.transactions.transfer'), [
                'wipe_cycle_id' => $wipe->id,
                'destination_type' => 'organization',
                'amount' => 9100,
                'description' => 'Treasury donation',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->assertSame('pending', $request->status);
        $this->assertTrue((bool) $request->destination_is_org_owned);
        $this->assertNull($request->incoming_transaction_id);

        $this->actingAs($approver)
            ->post(route('organization.ledger.transactions.transfer.approve', $request->id))
            ->assertRedirect();

        $request->refresh();

        $this->assertSame('completed', $request->status);
        $this->assertNotNull($request->incoming_transaction_id);
        $this->assertDatabaseHas('ledger_transactions', [
            'id' => $request->incoming_transaction_id,
            'is_org_owned' => true,
            'type' => 'income',
            'source_type' => 'transfer',
        ]);
    }

    public function test_completed_fund_transfer_cannot_be_approved_twice(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('TreasuryDonor', 'treasury-donor-repeat-approve');
        $approver = $this->directorUser();
        $wipe = $this->currentWipe();

        $this->actingAs($actor)
            ->post(route('ledger.transactions.transfer'), [
                'wipe_cycle_id' => $wipe->id,
                'destination_type' => 'organization',
                'amount' => 9100,
                'description' => 'Treasury donation',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->actingAs($approver)
            ->post(route('organization.ledger.transactions.transfer.approve', $request->id))
            ->assertRedirect();

        $this->actingAs($approver)
            ->post(route('organization.ledger.transactions.transfer.approve', $request->id))
            ->assertSessionHasErrors('transfer');

        $request->refresh();

        $this->assertSame('completed', $request->status);
    }

    public function test_personal_transfer_to_outside_horizon_completes_without_recipient(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('OutboundPilot', 'outbound-pilot');
        $wipe = $this->currentWipe();

        $this
            ->actingAs($actor)
            ->post(route('ledger.transactions.transfer'), [
                'wipe_cycle_id' => $wipe->id,
                'destination_type' => 'external',
                'amount' => 3200,
                'description' => 'Paid outside contractor',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->assertSame('completed', $request->status);
        $this->assertTrue((bool) $request->destination_is_external);
        $this->assertNotNull($request->outgoing_transaction_id);
        $this->assertNull($request->incoming_transaction_id);
        $this->assertDatabaseHas('ledger_transactions', [
            'id' => $request->outgoing_transaction_id,
            'user_id' => $actor->id,
            'type' => 'expense',
            'source_type' => 'transfer',
            'transfer_direction' => 'outgoing',
        ]);
        $this->assertSame(1, LedgerActivityLog::query()->where('action', 'transfer.created')->count());
        $this->assertSame(0, LedgerActivityLog::query()->where('action', 'transfer.requested')->count());
    }

    public function test_inventory_transfer_to_a_verified_member_creates_pending_request(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('CargoPilot', 'cargo-pilot');
        $recipient = $this->verifiedUser('CargoBuddy', 'cargo-buddy');
        $wipe = $this->currentWipe();

        $item = LedgerInventoryItem::query()->create([
            'user_id' => $actor->id,
            'wipe_cycle_id' => $wipe->id,
            'source_type' => 'commodity',
            'uex_reference_type' => 'commodity',
            'uex_reference_id' => 303,
            'custom_name' => 'Medical Supplies',
            'quantity' => 12,
            'unit_label' => 'SCU',
            'purchase_price' => 12000,
            'estimated_value' => 15600,
            'currency' => 'aUEC',
            'status' => 'owned',
            'acquired_at' => now()->subDay(),
        ]);

        $this
            ->actingAs($actor)
            ->post(route('ledger.inventory.transfer'), [
                'inventory_item_id' => $item->id,
                'destination_type' => 'personal',
                'destination_user_id' => $recipient->id,
                'quantity' => 4,
                'notes' => 'Hand-off to another pilot',
            ])
            ->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'inventory')->firstOrFail();

        $this->assertSame('pending', $request->status);
        $this->assertSame($recipient->id, $request->destination_user_id);
        $this->assertDatabaseMissing('ledger_inventory_items', [
            'transfer_request_id' => $request->id,
        ]);
    }

    public function test_org_manager_can_approve_pending_inventory_transfer_into_horizon_treasury(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('CargoPilot', 'cargo-pilot-org-approve');
        $approver = $this->directorUser();
        $wipe = $this->currentWipe();

        $item = LedgerInventoryItem::query()->create([
            'user_id' => $actor->id,
            'wipe_cycle_id' => $wipe->id,
            'source_type' => 'commodity',
            'uex_reference_type' => 'commodity',
            'uex_reference_id' => 707,
            'custom_name' => 'Refined Quantanium',
            'quantity' => 10,
            'unit_label' => 'SCU',
            'purchase_price' => 30000,
            'estimated_value' => 36000,
            'currency' => 'aUEC',
            'status' => 'owned',
            'acquired_at' => now()->subDay(),
        ]);

        $this
            ->actingAs($actor)
            ->post(route('ledger.inventory.transfer'), [
                'inventory_item_id' => $item->id,
                'destination_type' => 'organization',
                'quantity' => 4,
                'notes' => 'Reserve stock for Horizon',
            ])
            ->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'inventory')->firstOrFail();

        $this->assertSame('pending', $request->status);
        $this->assertTrue((bool) $request->destination_is_org_owned);
        $this->assertNull($request->destination_inventory_item_id);

        $this->actingAs($approver)
            ->post(route('organization.ledger.inventory.transfer.approve', $request->id))
            ->assertRedirect();

        $request->refresh();
        $item->refresh();

        $this->assertSame('completed', $request->status);
        $this->assertNotNull($request->destination_inventory_item_id);
        $this->assertSame('6.0000', $item->quantity);

        $this->assertDatabaseHas('ledger_inventory_items', [
            'id' => $request->destination_inventory_item_id,
            'is_org_owned' => true,
            'custom_name' => 'Refined Quantanium',
            'quantity' => '4.0000',
            'transfer_request_id' => $request->id,
        ]);
    }

    public function test_regular_member_cannot_approve_pending_inventory_transfer_into_horizon_treasury(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('CargoPilot', 'cargo-pilot-org-denied');
        $outsider = $this->verifiedUser('CargoVisitor', 'cargo-visitor');
        $wipe = $this->currentWipe();

        $item = LedgerInventoryItem::query()->create([
            'user_id' => $actor->id,
            'wipe_cycle_id' => $wipe->id,
            'source_type' => 'commodity',
            'uex_reference_type' => 'commodity',
            'uex_reference_id' => 818,
            'custom_name' => 'Hull Panels',
            'quantity' => 6,
            'unit_label' => 'SCU',
            'purchase_price' => 6000,
            'estimated_value' => 7200,
            'currency' => 'aUEC',
            'status' => 'owned',
            'acquired_at' => now()->subDay(),
        ]);

        $this->actingAs($actor)
            ->post(route('ledger.inventory.transfer'), [
                'inventory_item_id' => $item->id,
                'destination_type' => 'organization',
                'quantity' => 2,
                'notes' => 'Treasury stock top-up',
            ])
            ->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'inventory')->firstOrFail();

        $this->actingAs($outsider)
            ->post(route('organization.ledger.inventory.transfer.approve', $request->id))
            ->assertForbidden();

        $request->refresh();

        $this->assertSame('pending', $request->status);
        $this->assertNull($request->completed_at);
        $this->assertNull($request->destination_inventory_item_id);
    }

    public function test_partial_inventory_transfer_to_outside_horizon_reduces_source_without_creating_destination_item(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('CargoDropper', 'cargo-dropper');
        $wipe = $this->currentWipe();

        $item = LedgerInventoryItem::query()->create([
            'user_id' => $actor->id,
            'wipe_cycle_id' => $wipe->id,
            'source_type' => 'commodity',
            'uex_reference_type' => 'commodity',
            'uex_reference_id' => 909,
            'custom_name' => 'Emergency Rations',
            'quantity' => 9,
            'unit_label' => 'SCU',
            'purchase_price' => 9000,
            'estimated_value' => 10800,
            'currency' => 'aUEC',
            'status' => 'owned',
            'acquired_at' => now()->subDay(),
        ]);

        $this
            ->actingAs($actor)
            ->post(route('ledger.inventory.transfer'), [
                'inventory_item_id' => $item->id,
                'destination_type' => 'external',
                'quantity' => 4,
                'notes' => 'Delivered outside Horizon',
            ])
            ->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'inventory')->firstOrFail();

        $item->refresh();
        $this->assertSame('completed', $request->status);
        $this->assertTrue((bool) $request->destination_is_external);
        $this->assertNull($request->destination_inventory_item_id);
        $this->assertSame('5.0000', $item->quantity);
        $this->assertSame('5000.00', $item->purchase_price);
        $this->assertSame('6000.00', $item->estimated_value);
        $this->assertTrue((bool) $item->provenance_locked);
        $this->assertSame(1, LedgerActivityLog::query()->where('action', 'inventory.transfer_out')->count());
        $this->assertSame(0, LedgerActivityLog::query()->where('action', 'inventory.transfer_in')->count());
    }

    public function test_org_manager_can_reject_pending_inventory_transfer_into_horizon_treasury(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('CargoPilot', 'cargo-pilot-org-reject');
        $approver = $this->directorUser();
        $wipe = $this->currentWipe();

        $item = LedgerInventoryItem::query()->create([
            'user_id' => $actor->id,
            'wipe_cycle_id' => $wipe->id,
            'source_type' => 'commodity',
            'uex_reference_type' => 'commodity',
            'uex_reference_id' => 808,
            'custom_name' => 'Construction Materials',
            'quantity' => 7,
            'unit_label' => 'SCU',
            'purchase_price' => 7000,
            'estimated_value' => 9100,
            'currency' => 'aUEC',
            'status' => 'owned',
            'acquired_at' => now()->subDay(),
        ]);

        $this
            ->actingAs($actor)
            ->post(route('ledger.inventory.transfer'), [
                'inventory_item_id' => $item->id,
                'destination_type' => 'organization',
                'quantity' => 3,
                'notes' => 'Offer to Horizon stores',
            ])
            ->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'inventory')->firstOrFail();

        $this->actingAs($approver)
            ->post(route('organization.ledger.inventory.transfer.reject', $request->id), [
                'rejection_reason' => 'Treasury stock is already full',
            ])
            ->assertRedirect();

        $request->refresh();
        $item->refresh();

        $this->assertSame('rejected', $request->status);
        $this->assertSame('Treasury stock is already full', $request->rejection_reason);
        $this->assertNull($request->destination_inventory_item_id);
        $this->assertSame('7.0000', $item->quantity);
        $this->assertDatabaseMissing('ledger_inventory_items', [
            'transfer_request_id' => $request->id,
            'is_org_owned' => true,
        ]);
    }

    public function test_verified_member_can_approve_pending_personal_transfer_request(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('SenderHandle', 'sender-discord');
        $recipient = $this->verifiedUser('ReceiverHandle', 'receiver-discord');
        $wipe = $this->currentWipe();

        $this->actingAs($actor)->post(route('ledger.transactions.transfer'), [
            'wipe_cycle_id' => $wipe->id,
            'destination_type' => 'personal',
            'destination_user_id' => $recipient->id,
            'amount' => 6400,
            'description' => 'Reimbursement',
            'transaction_date' => now()->toDateTimeString(),
        ])->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->actingAs($recipient)
            ->post(route('ledger.transactions.transfer.approve', $request->id))
            ->assertRedirect();

        $request->refresh();

        $this->assertSame('completed', $request->status);
        $this->assertNotNull($request->approved_at);
        $this->assertDatabaseHas('ledger_transactions', [
            'id' => $request->outgoing_transaction_id,
            'user_id' => $actor->id,
            'type' => 'expense',
            'source_type' => 'transfer',
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'id' => $request->incoming_transaction_id,
            'user_id' => $recipient->id,
            'type' => 'income',
            'source_type' => 'transfer',
        ]);
    }

    public function test_unrelated_verified_member_cannot_approve_pending_personal_transfer_request(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('SenderHandle', 'sender-discord');
        $recipient = $this->verifiedUser('ReceiverHandle', 'receiver-discord');
        $stranger = $this->verifiedUser('ThirdPilot', 'third-pilot-discord');
        $wipe = $this->currentWipe();

        $this->actingAs($actor)->post(route('ledger.transactions.transfer'), [
            'wipe_cycle_id' => $wipe->id,
            'destination_type' => 'personal',
            'destination_user_id' => $recipient->id,
            'amount' => 6400,
            'description' => 'Reimbursement',
            'transaction_date' => now()->toDateTimeString(),
        ])->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->actingAs($stranger)
            ->post(route('ledger.transactions.transfer.approve', $request->id))
            ->assertSessionHasErrors('transfer');

        $request->refresh();

        $this->assertSame('pending', $request->status);
        $this->assertNull($request->approved_at);
        $this->assertNull($request->incoming_transaction_id);
    }

    public function test_squadron_manager_can_approve_pending_cross_squadron_transfer_request(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->directorUser();
        $sourceSquadron = $this->managedSquadronFor($actor, 'Atlas Finance');
        $recipientSquadron = $this->managedSquadronFor($actor, 'Prospectors Union');
        $wipe = $this->currentWipe();

        SquadronMember::query()
            ->where('squadron_id', $recipientSquadron->id)
            ->where('user_id', $actor->id)
            ->delete();

        $approver = $this->directorUser();
        $recipientSquadron->forceFill(['leader_id' => $approver->id])->save();
        SquadronMember::query()->create([
            'user_id' => $approver->id,
            'squadron_id' => $recipientSquadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_LEADER,
            'joined_at' => now()->subDays(5),
        ]);

        $this->actingAs($actor)->post(route('squadrons.ledger.transactions.transfer', ['squadron' => $sourceSquadron->id]), [
            'wipe_cycle_id' => $wipe->id,
            'destination_type' => 'squadron',
            'destination_squadron_id' => $recipientSquadron->id,
            'amount' => 9400,
            'description' => 'Cross-squadron reserve',
            'transaction_date' => now()->toDateTimeString(),
        ])->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->actingAs($approver)
            ->post(route('squadrons.ledger.transactions.transfer.approve', ['squadron' => $recipientSquadron->id, 'transferRequest' => $request->id]))
            ->assertRedirect();

        $request->refresh();

        $this->assertSame('completed', $request->status);
        $this->assertDatabaseHas('ledger_transactions', [
            'id' => $request->incoming_transaction_id,
            'squadron_id' => $recipientSquadron->id,
            'type' => 'income',
            'source_type' => 'transfer',
        ]);
    }

    public function test_squadron_manager_cannot_approve_transfer_through_the_wrong_squadron_ledger(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->directorUser();
        $sourceSquadron = $this->managedSquadronFor($actor, 'Atlas Finance');
        $recipientSquadron = $this->managedSquadronFor($actor, 'Prospectors Union');
        $wrongSquadron = $this->managedSquadronFor($actor, 'Wrong Inbox');
        $wipe = $this->currentWipe();

        $this->actingAs($actor)->post(route('squadrons.ledger.transactions.transfer', ['squadron' => $sourceSquadron->id]), [
            'wipe_cycle_id' => $wipe->id,
            'destination_type' => 'squadron',
            'destination_squadron_id' => $recipientSquadron->id,
            'amount' => 9400,
            'description' => 'Cross-squadron reserve',
            'transaction_date' => now()->toDateTimeString(),
        ])->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->actingAs($actor)
            ->post(route('squadrons.ledger.transactions.transfer.approve', ['squadron' => $wrongSquadron->id, 'transferRequest' => $request->id]))
            ->assertSessionHasErrors('transfer');

        $request->refresh();

        $this->assertSame('pending', $request->status);
        $this->assertNull($request->approved_at);
        $this->assertNull($request->incoming_transaction_id);
    }

    public function test_pending_transfer_request_can_be_rejected(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('SenderHandle', 'sender-discord');
        $recipient = $this->verifiedUser('ReceiverHandle', 'receiver-discord');
        $wipe = $this->currentWipe();

        $this->actingAs($actor)->post(route('ledger.transactions.transfer'), [
            'wipe_cycle_id' => $wipe->id,
            'destination_type' => 'personal',
            'destination_user_id' => $recipient->id,
            'amount' => 6400,
            'description' => 'Reimbursement',
            'transaction_date' => now()->toDateTimeString(),
        ])->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->actingAs($recipient)
            ->post(route('ledger.transactions.transfer.reject', $request->id), [
                'rejection_reason' => 'Wrong member',
            ])
            ->assertRedirect();

        $request->refresh();

        $this->assertSame('rejected', $request->status);
        $this->assertSame('Wrong member', $request->rejection_reason);
        $this->assertNull($request->incoming_transaction_id);
        $this->assertDatabaseMissing('ledger_transactions', [
            'transfer_request_id' => $request->id,
        ]);
    }

    public function test_rejected_transfer_request_cannot_be_rejected_twice(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('SenderHandle', 'sender-discord-repeat-reject');
        $recipient = $this->verifiedUser('ReceiverHandle', 'receiver-discord-repeat-reject');
        $wipe = $this->currentWipe();

        $this->actingAs($actor)->post(route('ledger.transactions.transfer'), [
            'wipe_cycle_id' => $wipe->id,
            'destination_type' => 'personal',
            'destination_user_id' => $recipient->id,
            'amount' => 6400,
            'description' => 'Reimbursement',
            'transaction_date' => now()->toDateTimeString(),
        ])->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->actingAs($recipient)
            ->post(route('ledger.transactions.transfer.reject', $request->id), [
                'rejection_reason' => 'Wrong member',
            ])
            ->assertRedirect();

        $this->actingAs($recipient)
            ->post(route('ledger.transactions.transfer.reject', $request->id), [
                'rejection_reason' => 'Still wrong member',
            ])
            ->assertSessionHasErrors('transfer');

        $request->refresh();

        $this->assertSame('rejected', $request->status);
        $this->assertSame('Wrong member', $request->rejection_reason);
    }

    public function test_squadron_manager_cannot_reject_transfer_through_the_wrong_squadron_ledger(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->directorUser();
        $sourceSquadron = $this->managedSquadronFor($actor, 'Atlas Finance');
        $recipientSquadron = $this->managedSquadronFor($actor, 'Prospectors Union');
        $wrongSquadron = $this->managedSquadronFor($actor, 'Wrong Inbox');
        $wipe = $this->currentWipe();

        $this->actingAs($actor)->post(route('squadrons.ledger.transactions.transfer', ['squadron' => $sourceSquadron->id]), [
            'wipe_cycle_id' => $wipe->id,
            'destination_type' => 'squadron',
            'destination_squadron_id' => $recipientSquadron->id,
            'amount' => 9400,
            'description' => 'Cross-squadron reserve',
            'transaction_date' => now()->toDateTimeString(),
        ])->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->actingAs($actor)
            ->post(route('squadrons.ledger.transactions.transfer.reject', [
                'squadron' => $wrongSquadron->id,
                'transferRequest' => $request->id,
            ]), [
                'rejection_reason' => 'Wrong inbox',
            ])
            ->assertSessionHasErrors('transfer');

        $request->refresh();

        $this->assertSame('pending', $request->status);
        $this->assertNull($request->rejected_at);
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

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->actingAs($actor)
            ->post(route('organization.ledger.transactions.transfer.approve', $request->id))
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

    public function test_completed_fund_transfer_can_be_reversed(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->directorUser();
        $wipe = $this->currentWipe();

        $this->actingAs($actor)->post(route('ledger.transactions.transfer'), [
            'wipe_cycle_id' => $wipe->id,
            'destination_type' => 'organization',
            'amount' => 9000,
            'description' => 'Treasury top-up',
            'transaction_date' => now()->toDateTimeString(),
        ])->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->actingAs($actor)
            ->post(route('organization.ledger.transactions.transfer.approve', $request->id))
            ->assertRedirect();

        $this->actingAs($actor)
            ->post(route('ledger.transactions.transfer.reverse', $request->id), [
                'reversal_notes' => 'Sent in error',
            ])
            ->assertRedirect();

        $request->refresh();

        $this->assertSame('reversed', $request->status);
        $this->assertNotNull($request->reversal_outgoing_transaction_id);
        $this->assertNotNull($request->reversal_incoming_transaction_id);
        $this->assertDatabaseHas('ledger_transactions', [
            'id' => $request->reversal_outgoing_transaction_id,
            'type' => 'income',
            'source_type' => 'transfer_reversal',
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'id' => $request->reversal_incoming_transaction_id,
            'type' => 'expense',
            'source_type' => 'transfer_reversal',
        ]);
    }

    public function test_reversed_transfer_cannot_be_reversed_twice(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->directorUser();
        $wipe = $this->currentWipe();

        $this->actingAs($actor)->post(route('ledger.transactions.transfer'), [
            'wipe_cycle_id' => $wipe->id,
            'destination_type' => 'organization',
            'amount' => 9000,
            'description' => 'Treasury top-up',
            'transaction_date' => now()->toDateTimeString(),
        ])->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->actingAs($actor)
            ->post(route('organization.ledger.transactions.transfer.approve', $request->id))
            ->assertRedirect();

        $this->actingAs($actor)
            ->post(route('ledger.transactions.transfer.reverse', $request->id), [
                'reversal_notes' => 'Sent in error',
            ])
            ->assertRedirect();

        $this->actingAs($actor)
            ->post(route('ledger.transactions.transfer.reverse', $request->id), [
                'reversal_notes' => 'Trying it again',
            ])
            ->assertSessionHasErrors('transfer');

        $request->refresh();

        $this->assertSame('reversed', $request->status);
    }

    public function test_archived_cycle_completed_transfer_cannot_be_reversed(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->directorUser();
        $wipe = $this->currentWipe();

        $this->actingAs($actor)->post(route('ledger.transactions.transfer'), [
            'wipe_cycle_id' => $wipe->id,
            'destination_type' => 'organization',
            'amount' => 9000,
            'description' => 'Treasury top-up',
            'transaction_date' => now()->toDateTimeString(),
        ])->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->actingAs($actor)
            ->post(route('organization.ledger.transactions.transfer.approve', $request->id))
            ->assertRedirect();

        $wipe->forceFill([
            'is_current' => false,
            'ended_at' => now(),
        ])->save();

        $this->actingAs($actor)
            ->post(route('ledger.transactions.transfer.reverse', $request->id), [
                'reversal_notes' => 'Too late to reverse',
            ])
            ->assertSessionHasErrors('wipe_cycle_id');

        $request->refresh();

        $this->assertSame('completed', $request->status);
        $this->assertNull($request->reversed_at);
    }

    public function test_completed_external_fund_transfer_can_be_reversed_without_destination_reversal(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('ReverseOutbound', 'reverse-outbound');
        $wipe = $this->currentWipe();

        $this->actingAs($actor)->post(route('ledger.transactions.transfer'), [
            'wipe_cycle_id' => $wipe->id,
            'destination_type' => 'external',
            'amount' => 4700,
            'description' => 'Sent outside Horizon',
            'transaction_date' => now()->toDateTimeString(),
        ])->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->actingAs($actor)
            ->post(route('ledger.transactions.transfer.reverse', $request->id), [
                'reversal_notes' => 'Should have stayed internal',
            ])
            ->assertRedirect();

        $request->refresh();

        $this->assertSame('reversed', $request->status);
        $this->assertNotNull($request->reversal_outgoing_transaction_id);
        $this->assertNull($request->reversal_incoming_transaction_id);
        $this->assertDatabaseHas('ledger_transactions', [
            'id' => $request->reversal_outgoing_transaction_id,
            'type' => 'income',
            'source_type' => 'transfer_reversal',
        ]);
    }

    public function test_unrelated_verified_member_cannot_reverse_completed_transfer_that_does_not_touch_their_ledger(): void
    {
        config()->set('services.ledger.enabled', true);

        $actor = $this->verifiedUser('ReverseOutbound', 'reverse-outbound');
        $stranger = $this->verifiedUser('NotMyTransfer', 'not-my-transfer');
        $wipe = $this->currentWipe();

        $this->actingAs($actor)->post(route('ledger.transactions.transfer'), [
            'wipe_cycle_id' => $wipe->id,
            'destination_type' => 'external',
            'amount' => 4700,
            'description' => 'Sent outside Horizon',
            'transaction_date' => now()->toDateTimeString(),
        ])->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'funds')->firstOrFail();

        $this->actingAs($stranger)
            ->post(route('ledger.transactions.transfer.reverse', $request->id), [
                'reversal_notes' => 'Trying to reverse someone else',
            ])
            ->assertSessionHasErrors('transfer');

        $request->refresh();

        $this->assertSame('completed', $request->status);
        $this->assertNull($request->reversed_at);
        $this->assertNull($request->reversal_outgoing_transaction_id);
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

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'inventory')->firstOrFail();

        $this->actingAs($actor)
            ->post(route('squadrons.ledger.inventory.transfer.approve', ['squadron' => $squadron->id, 'transferRequest' => $request->id]))
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

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'inventory')->firstOrFail();

        $this->actingAs($actor)
            ->post(route('organization.ledger.inventory.transfer.approve', $request->id))
            ->assertRedirect();

        $item->refresh();

        $this->assertTrue((bool) $item->is_org_owned);
        $this->assertNull($item->squadron_id);
        $this->assertSame($actor->id, $item->user_id);
        $this->assertTrue((bool) $item->provenance_locked);
        $this->assertStringContainsString('Moved into treasury stock', (string) $item->notes);
    }

    public function test_transferred_inventory_records_are_locked_from_manual_edits_and_deletes(): void
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

        $this->actingAs($actor)->post(route('ledger.inventory.transfer'), [
            'inventory_item_id' => $item->id,
            'destination_type' => 'organization',
            'quantity' => 1,
            'notes' => 'Moved into treasury stock',
        ])->assertRedirect();

        $request = LedgerTransferRequest::query()->where('transfer_kind', 'inventory')->firstOrFail();

        $this->actingAs($actor)
            ->post(route('organization.ledger.inventory.transfer.approve', $request->id))
            ->assertRedirect();

        $item->refresh();

        $this->assertTrue((bool) $item->provenance_locked);

        $this->actingAs($actor)
            ->put(route('ledger.inventory.update', $item->id), [
                'wipe_cycle_id' => $wipe->id,
                'source_type' => 'item',
                'uex_reference_type' => 'item',
                'uex_reference_id' => 202,
                'custom_name' => 'Atlas Drive',
                'category' => 'drive',
                'quantity' => 1,
                'unit_label' => 'units',
                'purchase_price' => 4000,
                'estimated_value' => 4500,
                'currency' => 'aUEC',
                'status' => 'owned',
                'acquired_at' => now()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('inventory');

        $this->actingAs($actor)
            ->delete(route('ledger.inventory.destroy', $item->id))
            ->assertSessionHasErrors('inventory');
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

    protected function memberSquadronFor(User $actor, string $name): Squadron
    {
        $squadron = Squadron::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'status' => 'active',
            'branch' => 'logistics',
            'division' => 'support',
            'leader_id' => $actor->id,
            'recruiting' => true,
        ]);

        SquadronMember::query()->create([
            'user_id' => $actor->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_MEMBER,
            'joined_at' => now()->subDays(7),
        ]);

        return $squadron;
    }

    protected function unmanagedSquadron(string $name): Squadron
    {
        return Squadron::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'status' => 'active',
            'branch' => 'resource',
            'division' => 'shared',
            'leader_id' => null,
            'recruiting' => true,
        ]);
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

    protected function verifiedUser(string $rsiHandle, string $discordId): User
    {
        $role = Role::query()->where('slug', 'cit')->firstOrFail();

        $user = User::factory()->create([
            'discord_id' => $discordId,
            'discord_name' => $rsiHandle,
            'rsi_handle' => $rsiHandle,
            'rank' => 'member',
            'rank_level' => 1,
            'rsi_verified_at' => now(),
            'global_status' => User::STATUS_ACTIVE,
        ]);

        $user->roles()->attach($role->id);
        $user->load('roles');

        return $user;
    }
}
