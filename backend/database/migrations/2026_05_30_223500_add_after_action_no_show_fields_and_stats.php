<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            if (! Schema::hasColumn('operations', 'after_action_no_show_user_ids')) {
                $table->json('after_action_no_show_user_ids')->nullable()->after('after_action_attendance_user_ids');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'operations_no_show_count')) {
                $table->unsignedInteger('operations_no_show_count')->default(0)->after('operations_completed_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            if (Schema::hasColumn('operations', 'after_action_no_show_user_ids')) {
                $table->dropColumn('after_action_no_show_user_ids');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'operations_no_show_count')) {
                $table->dropColumn('operations_no_show_count');
            }
        });
    }
};
