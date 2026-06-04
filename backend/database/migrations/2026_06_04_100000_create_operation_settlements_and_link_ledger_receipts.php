<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('money_rows')->nullable();
            $table->json('loot_rows')->nullable();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->index();
            $table->foreignId('reopened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamps();
        });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->foreignId('operation_settlement_id')->nullable()->after('related_operation_id')->constrained('operation_settlements')->nullOnDelete();
            $table->boolean('provenance_locked')->default(false)->after('notes')->index();
        });

        Schema::table('ledger_inventory_items', function (Blueprint $table) {
            $table->foreignId('related_operation_id')->nullable()->after('wipe_cycle_id')->constrained('operations')->nullOnDelete();
            $table->foreignId('operation_settlement_id')->nullable()->after('related_operation_id')->constrained('operation_settlements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ledger_inventory_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operation_settlement_id');
            $table->dropConstrainedForeignId('related_operation_id');
        });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->dropIndex(['provenance_locked']);
            $table->dropColumn('provenance_locked');
            $table->dropConstrainedForeignId('operation_settlement_id');
        });

        Schema::dropIfExists('operation_settlements');
    }
};
