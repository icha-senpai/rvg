<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationSchemaAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_tables_include_columns_required_by_current_runtime_and_reporting_code(): void
    {
        $this->assertSame([], array_values(array_diff([
            'role_name',
            'role_display_name',
            'capacity',
            'min_required',
            'description',
            'requirements',
            'sort_order',
            'is_required',
        ], Schema::getColumnListing('operation_roles'))));

        $this->assertSame([], array_values(array_diff([
            'operation_role_id',
            'operation_discord_channel_id',
            'runtime_status',
            'runtime_source',
            'signed_off_at',
            'synced_in_at',
            'starting_auec',
            'ending_auec',
            'runtime_notes',
        ], Schema::getColumnListing('operation_participants'))));

        $this->assertSame([], array_values(array_diff([
            'money_rows',
            'loot_rows',
            'prep_money_rows',
            'prep_money_rows_updated_by_user_id',
            'prep_money_rows_updated_at',
            'finalized_by_user_id',
            'finalized_at',
            'reopened_by_user_id',
            'reopened_at',
        ], Schema::getColumnListing('operation_settlements'))));

        $this->assertSame([], array_values(array_diff([
            'synced_by_user_id',
            'synced_at',
            'source_channel_ids',
            'present_user_ids',
            'no_show_user_ids',
            'walk_in_user_ids',
        ], Schema::getColumnListing('operation_sync_runs'))));

        $this->assertSame([], array_values(array_diff([
            'operation_id',
            'name',
            'discord_channel_id',
            'sort_order',
        ], Schema::getColumnListing('operation_discord_channels'))));

        $this->assertSame([], array_values(array_diff([
            'squadron_name',
            'operation_type',
            'branch',
            'gameplay_type',
            'start_location',
            'operation_location',
            'extended_description',
            'completion_outcome',
            'after_action_report',
            'after_action_attendance_user_ids',
            'after_action_no_show_user_ids',
            'after_action_signed_off_early_user_ids',
            'after_action_excused_user_ids',
            'after_action_report_updated_at',
            'discord_message_id',
            'discord_message_targets',
        ], Schema::getColumnListing('operations'))));

        $this->assertSame([], array_values(array_diff([
            'operations_joined_count',
            'operations_left_early_count',
            'operations_completed_count',
            'operations_no_show_count',
            'operations_excused_count',
            'operations_created_count',
            'operations_canceled_count',
            'operations_success_count',
            'operations_failed_count',
        ], Schema::getColumnListing('users'))));
    }
}
