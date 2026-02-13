<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'operations_created_count')) {
                $table->unsignedInteger('operations_created_count')->default(0);
            }

            if (! Schema::hasColumn('users', 'operations_canceled_count')) {
                $table->unsignedInteger('operations_canceled_count')->default(0);
            }

            if (! Schema::hasColumn('users', 'operations_success_count')) {
                $table->unsignedInteger('operations_success_count')->default(0);
            }

            if (! Schema::hasColumn('users', 'operations_failed_count')) {
                $table->unsignedInteger('operations_failed_count')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'operations_created_count')) {
                $table->dropColumn('operations_created_count');
            }

            if (Schema::hasColumn('users', 'operations_canceled_count')) {
                $table->dropColumn('operations_canceled_count');
            }

            if (Schema::hasColumn('users', 'operations_success_count')) {
                $table->dropColumn('operations_success_count');
            }

            if (Schema::hasColumn('users', 'operations_failed_count')) {
                $table->dropColumn('operations_failed_count');
            }
        });
    }
};
