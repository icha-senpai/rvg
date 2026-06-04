<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_kind')->index();
            $table->string('status')->default('pending')->index();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approval_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('wipe_cycle_id')->nullable()->constrained('wipe_cycles')->nullOnDelete();

            $table->foreignId('source_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_squadron_id')->nullable()->constrained('squadrons')->nullOnDelete();
            $table->boolean('source_is_org_owned')->default(false)->index();

            $table->foreignId('destination_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('destination_squadron_id')->nullable()->constrained('squadrons')->nullOnDelete();
            $table->boolean('destination_is_org_owned')->default(false)->index();

            $table->decimal('amount', 18, 2)->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('currency', 16)->default('aUEC')->index();
            $table->string('description');
            $table->timestamp('transaction_date')->nullable()->index();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('reversal_notes')->nullable();

            $table->foreignId('source_inventory_item_id')->nullable()->constrained('ledger_inventory_items')->nullOnDelete();
            $table->foreignId('destination_inventory_item_id')->nullable()->constrained('ledger_inventory_items')->nullOnDelete();
            $table->foreignId('outgoing_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->foreignId('incoming_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->foreignId('reversal_outgoing_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->foreignId('reversal_incoming_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();

            $table->timestamp('approved_at')->nullable()->index();
            $table->timestamp('rejected_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamp('reversed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->foreignId('transfer_request_id')->nullable()->after('wipe_cycle_id')->constrained('ledger_transfer_requests')->nullOnDelete();
            $table->string('transfer_direction')->nullable()->after('source_type')->index();
            $table->foreignId('reversal_of_transaction_id')->nullable()->after('transfer_request_id')->constrained('ledger_transactions')->nullOnDelete();
        });

        Schema::table('ledger_inventory_items', function (Blueprint $table) {
            $table->foreignId('transfer_request_id')->nullable()->after('wipe_cycle_id')->constrained('ledger_transfer_requests')->nullOnDelete();
            $table->foreignId('transfer_origin_item_id')->nullable()->after('transfer_request_id')->constrained('ledger_inventory_items')->nullOnDelete();
            $table->boolean('provenance_locked')->default(false)->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('ledger_inventory_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transfer_origin_item_id');
            $table->dropConstrainedForeignId('transfer_request_id');
            $table->dropIndex(['provenance_locked']);
            $table->dropColumn('provenance_locked');
        });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_of_transaction_id');
            $table->dropConstrainedForeignId('transfer_request_id');
            $table->dropIndex(['transfer_direction']);
            $table->dropColumn('transfer_direction');
        });

        Schema::dropIfExists('ledger_transfer_requests');
    }
};
