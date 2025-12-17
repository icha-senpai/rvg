<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('squadrons', function (Blueprint $table) {
            $table->foreignId('leader_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('status');
        });

        DB::statement("update squadrons set leader_id = (\n            select sm.user_id\n            from squadron_members sm\n            where sm.squadron_id = squadrons.id\n              and sm.role = 'leader'\n              and sm.membership_status = 'active'\n            limit 1\n        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('squadrons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leader_id');
        });
    }
};
