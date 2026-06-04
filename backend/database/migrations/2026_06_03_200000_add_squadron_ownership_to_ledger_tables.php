<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->foreignId('squadron_id')->nullable()->after('user_id')->constrained('squadrons')->nullOnDelete();
            $table->index(['squadron_id', 'type']);
        });

        Schema::table('ledger_ship_assets', function (Blueprint $table) {
            $table->foreignId('squadron_id')->nullable()->after('user_id')->constrained('squadrons')->nullOnDelete();
            $table->index(['squadron_id', 'wipe_cycle_id']);
        });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->foreignId('squadron_id')->nullable()->after('user_id')->constrained('squadrons')->nullOnDelete();
            $table->index(['squadron_id', 'wipe_cycle_id', 'type']);
        });

        Schema::table('ledger_trades', function (Blueprint $table) {
            $table->foreignId('squadron_id')->nullable()->after('user_id')->constrained('squadrons')->nullOnDelete();
            $table->index(['squadron_id', 'wipe_cycle_id']);
        });

        Schema::table('ledger_inventory_items', function (Blueprint $table) {
            $table->foreignId('squadron_id')->nullable()->after('user_id')->constrained('squadrons')->nullOnDelete();
            $table->index(['squadron_id', 'wipe_cycle_id', 'source_type']);
        });

        Schema::table('ledger_activity_logs', function (Blueprint $table) {
            $table->foreignId('squadron_id')->nullable()->after('subject_user_id')->constrained('squadrons')->nullOnDelete();
            $table->index(['squadron_id', 'wipe_cycle_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ledger_activity_logs', function (Blueprint $table) {
            $table->dropIndex(['squadron_id', 'wipe_cycle_id']);
            $table->dropConstrainedForeignId('squadron_id');
        });

        Schema::table('ledger_inventory_items', function (Blueprint $table) {
            $table->dropIndex(['squadron_id', 'wipe_cycle_id', 'source_type']);
            $table->dropConstrainedForeignId('squadron_id');
        });

        Schema::table('ledger_trades', function (Blueprint $table) {
            $table->dropIndex(['squadron_id', 'wipe_cycle_id']);
            $table->dropConstrainedForeignId('squadron_id');
        });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->dropIndex(['squadron_id', 'wipe_cycle_id', 'type']);
            $table->dropConstrainedForeignId('squadron_id');
        });

        Schema::table('ledger_ship_assets', function (Blueprint $table) {
            $table->dropIndex(['squadron_id', 'wipe_cycle_id']);
            $table->dropConstrainedForeignId('squadron_id');
        });

        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropIndex(['squadron_id', 'type']);
            $table->dropConstrainedForeignId('squadron_id');
        });
    }
};
