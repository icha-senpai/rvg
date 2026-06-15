<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            if (! Schema::hasColumn('operations', 'after_action_excused_user_ids')) {
                $table->json('after_action_excused_user_ids')
                    ->nullable()
                    ->after('after_action_signed_off_early_user_ids');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'operations_excused_count')) {
                $table->unsignedInteger('operations_excused_count')
                    ->default(0)
                    ->after('operations_no_show_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            if (Schema::hasColumn('operations', 'after_action_excused_user_ids')) {
                $table->dropColumn('after_action_excused_user_ids');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'operations_excused_count')) {
                $table->dropColumn('operations_excused_count');
            }
        });
    }
};
