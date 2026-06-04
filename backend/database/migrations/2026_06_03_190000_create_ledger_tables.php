<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wipe_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('star_citizen_version')->nullable()->index();
            $table->string('wipe_type')->default('unknown')->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable()->index();
            $table->boolean('is_current')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('personal')->index();
            $table->string('currency', 16)->default('aUEC')->index();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_archived')->default(false)->index();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });

        Schema::create('ledger_ship_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wipe_cycle_id')->constrained('wipe_cycles');
            $table->unsignedBigInteger('vehicle_uex_id')->nullable()->index();
            $table->string('custom_name')->nullable();
            $table->string('serial_or_label')->nullable()->index();
            $table->decimal('purchase_price', 18, 2)->nullable();
            $table->string('currency', 16)->default('aUEC')->index();
            $table->string('acquisition_source')->nullable()->index();
            $table->string('current_location')->nullable()->index();
            $table->string('status')->default('owned')->index();
            $table->timestamp('acquired_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'wipe_cycle_id']);
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained('ledger_accounts')->cascadeOnDelete();
            $table->foreignId('wipe_cycle_id')->constrained('wipe_cycles');
            $table->string('type')->index();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 16)->default('aUEC')->index();
            $table->string('source_type')->nullable()->index();
            $table->string('description');
            $table->timestamp('transaction_date')->index();
            $table->foreignId('related_ship_asset_id')->nullable()->constrained('ledger_ship_assets')->nullOnDelete();
            $table->foreignId('related_operation_id')->nullable()->constrained('operations')->nullOnDelete();
            $table->string('related_uex_type')->nullable()->index();
            $table->unsignedBigInteger('related_uex_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'wipe_cycle_id', 'type']);
        });

        Schema::create('ledger_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained('ledger_accounts')->cascadeOnDelete();
            $table->foreignId('wipe_cycle_id')->constrained('wipe_cycles');
            $table->unsignedBigInteger('commodity_uex_id')->nullable()->index();
            $table->unsignedBigInteger('buy_terminal_uex_id')->nullable()->index();
            $table->unsignedBigInteger('sell_terminal_uex_id')->nullable()->index();
            $table->decimal('quantity', 18, 4);
            $table->string('unit_type', 32)->default('SCU')->index();
            $table->decimal('buy_price_per_unit', 18, 2);
            $table->decimal('sell_price_per_unit', 18, 2);
            $table->decimal('total_cost', 18, 2);
            $table->decimal('total_revenue', 18, 2);
            $table->decimal('profit', 18, 2);
            $table->decimal('profit_per_unit', 18, 2);
            $table->foreignId('ship_asset_id')->nullable()->constrained('ledger_ship_assets')->nullOnDelete();
            $table->decimal('cargo_capacity_used', 18, 4)->nullable();
            $table->timestamp('trade_date')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'wipe_cycle_id']);
        });

        Schema::create('ledger_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wipe_cycle_id')->constrained('wipe_cycles');
            $table->string('source_type')->index();
            $table->string('uex_reference_type')->nullable()->index();
            $table->unsignedBigInteger('uex_reference_id')->nullable()->index();
            $table->string('custom_name')->nullable()->index();
            $table->string('category')->nullable()->index();
            $table->decimal('quantity', 18, 4)->default(1);
            $table->string('unit_label', 32)->nullable();
            $table->string('location_name')->nullable()->index();
            $table->unsignedBigInteger('terminal_uex_id')->nullable()->index();
            $table->foreignId('assigned_ship_asset_id')->nullable()->constrained('ledger_ship_assets')->nullOnDelete();
            $table->decimal('purchase_price', 18, 2)->nullable();
            $table->decimal('estimated_value', 18, 2)->nullable();
            $table->string('currency', 16)->default('aUEC')->index();
            $table->string('status')->default('owned')->index();
            $table->timestamp('acquired_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'wipe_cycle_id', 'source_type']);
        });

        Schema::create('ledger_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('wipe_cycle_id')->nullable()->constrained('wipe_cycles')->nullOnDelete();
            $table->string('action')->index();
            $table->string('target_type')->index();
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_activity_logs');
        Schema::dropIfExists('ledger_inventory_items');
        Schema::dropIfExists('ledger_trades');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_ship_assets');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('wipe_cycles');
    }
};
