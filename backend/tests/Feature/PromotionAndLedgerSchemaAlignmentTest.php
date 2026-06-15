<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PromotionAndLedgerSchemaAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotion_and_ledger_transfer_tables_include_columns_required_by_current_workflows(): void
    {
        $this->assertSame([], array_values(array_diff([
            'member_id',
            'promoter_id',
            'cancelled_by_user_id',
            'from_rank',
            'to_rank',
            'discord_branch_role_id',
            'discord_dm_message_id',
            'discord_quarter_message_id',
            'state',
            'expires_at',
            'accepted_at',
            'cancelled_at',
            'expired_at',
        ], Schema::getColumnListing('promotion_offers'))));

        $this->assertSame([], array_values(array_diff([
            'transfer_kind',
            'status',
            'requested_by_user_id',
            'approval_user_id',
            'reversal_user_id',
            'wipe_cycle_id',
            'source_user_id',
            'source_squadron_id',
            'source_is_org_owned',
            'destination_user_id',
            'destination_squadron_id',
            'destination_is_org_owned',
            'destination_is_external',
            'amount',
            'quantity',
            'currency',
            'description',
            'transaction_date',
            'notes',
            'rejection_reason',
            'reversal_notes',
            'source_inventory_item_id',
            'destination_inventory_item_id',
            'outgoing_transaction_id',
            'incoming_transaction_id',
            'reversal_outgoing_transaction_id',
            'reversal_incoming_transaction_id',
            'approved_at',
            'rejected_at',
            'completed_at',
            'reversed_at',
        ], Schema::getColumnListing('ledger_transfer_requests'))));

        $this->assertSame([], array_values(array_diff([
            'transfer_request_id',
            'reversal_of_transaction_id',
            'transfer_direction',
            'operation_settlement_id',
            'provenance_locked',
        ], Schema::getColumnListing('ledger_transactions'))));

        $this->assertSame([], array_values(array_diff([
            'transfer_request_id',
            'transfer_origin_item_id',
            'operation_settlement_id',
            'related_operation_id',
            'provenance_locked',
        ], Schema::getColumnListing('ledger_inventory_items'))));
    }
}
