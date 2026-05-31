<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->longText('after_action_report')->nullable()->after('completion_outcome');
            $table->json('after_action_attendance_user_ids')->nullable()->after('after_action_report');
            $table->timestamp('after_action_report_updated_at')->nullable()->after('after_action_attendance_user_ids');
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn([
                'after_action_report',
                'after_action_attendance_user_ids',
                'after_action_report_updated_at',
            ]);
        });
    }
};
