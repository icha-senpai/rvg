<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'operations_joined_count')) {
                $table->unsignedInteger('operations_joined_count')->default(0);
            }

            if (! Schema::hasColumn('users', 'operations_left_early_count')) {
                $table->unsignedInteger('operations_left_early_count')->default(0);
            }

            if (! Schema::hasColumn('users', 'operations_completed_count')) {
                $table->unsignedInteger('operations_completed_count')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'operations_joined_count')) {
                $table->dropColumn('operations_joined_count');
            }

            if (Schema::hasColumn('users', 'operations_left_early_count')) {
                $table->dropColumn('operations_left_early_count');
            }

            if (Schema::hasColumn('users', 'operations_completed_count')) {
                $table->dropColumn('operations_completed_count');
            }
        });
    }
};
