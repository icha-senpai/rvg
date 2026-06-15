<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            if (! Schema::hasColumn('operations', 'after_action_signed_off_early_user_ids')) {
                $table->json('after_action_signed_off_early_user_ids')
                    ->nullable()
                    ->after('after_action_no_show_user_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            if (Schema::hasColumn('operations', 'after_action_signed_off_early_user_ids')) {
                $table->dropColumn('after_action_signed_off_early_user_ids');
            }
        });
    }
};
