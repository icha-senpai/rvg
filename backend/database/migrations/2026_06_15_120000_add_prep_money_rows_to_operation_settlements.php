<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_settlements', function (Blueprint $table) {
            $table->json('prep_money_rows')->nullable()->after('loot_rows');
            $table->foreignId('prep_money_rows_updated_by_user_id')
                ->nullable()
                ->after('prep_money_rows')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('prep_money_rows_updated_at')->nullable()->after('prep_money_rows_updated_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('operation_settlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prep_money_rows_updated_by_user_id');
            $table->dropColumn('prep_money_rows_updated_at');
            $table->dropColumn('prep_money_rows');
        });
    }
};
