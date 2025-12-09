<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_participants', function (Blueprint $table) {
            // Only drop if it actually exists
            if (Schema::hasColumn('operation_participants', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('operation_participants', function (Blueprint $table) {
            // Restore soft deletes if rolled back
            if (!Schema::hasColumn('operation_participants', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }
};
