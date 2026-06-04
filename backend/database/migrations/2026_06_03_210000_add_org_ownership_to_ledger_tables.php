<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->boolean('is_org_owned')->default(false)->after('squadron_id')->index();
        });

        Schema::table('ledger_ship_assets', function (Blueprint $table) {
            $table->boolean('is_org_owned')->default(false)->after('squadron_id')->index();
        });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->boolean('is_org_owned')->default(false)->after('squadron_id')->index();
        });

        Schema::table('ledger_trades', function (Blueprint $table) {
            $table->boolean('is_org_owned')->default(false)->after('squadron_id')->index();
        });

        Schema::table('ledger_inventory_items', function (Blueprint $table) {
            $table->boolean('is_org_owned')->default(false)->after('squadron_id')->index();
        });

        Schema::table('ledger_activity_logs', function (Blueprint $table) {
            $table->boolean('is_org_owned')->default(false)->after('squadron_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('ledger_activity_logs', function (Blueprint $table) {
            $table->dropIndex(['is_org_owned']);
            $table->dropColumn('is_org_owned');
        });

        Schema::table('ledger_inventory_items', function (Blueprint $table) {
            $table->dropIndex(['is_org_owned']);
            $table->dropColumn('is_org_owned');
        });

        Schema::table('ledger_trades', function (Blueprint $table) {
            $table->dropIndex(['is_org_owned']);
            $table->dropColumn('is_org_owned');
        });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->dropIndex(['is_org_owned']);
            $table->dropColumn('is_org_owned');
        });

        Schema::table('ledger_ship_assets', function (Blueprint $table) {
            $table->dropIndex(['is_org_owned']);
            $table->dropColumn('is_org_owned');
        });

        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropIndex(['is_org_owned']);
            $table->dropColumn('is_org_owned');
        });
    }
};
