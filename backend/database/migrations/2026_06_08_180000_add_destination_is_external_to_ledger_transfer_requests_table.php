<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_transfer_requests', function (Blueprint $table) {
            $table->boolean('destination_is_external')
                ->default(false)
                ->after('destination_is_org_owned')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('ledger_transfer_requests', function (Blueprint $table) {
            $table->dropIndex(['destination_is_external']);
            $table->dropColumn('destination_is_external');
        });
    }
};
