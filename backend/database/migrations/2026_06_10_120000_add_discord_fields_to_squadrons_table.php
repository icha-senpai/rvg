<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('squadrons', function (Blueprint $table) {
            $table->string('discord_channel_id')->nullable()->after('leader_id');
            $table->string('discord_sync_status')->default('not_linked')->after('discord_channel_id');
            $table->timestamp('discord_last_synced_at')->nullable()->after('discord_sync_status');
            $table->text('discord_sync_error')->nullable()->after('discord_last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('squadrons', function (Blueprint $table) {
            $table->dropColumn([
                'discord_channel_id',
                'discord_sync_status',
                'discord_last_synced_at',
                'discord_sync_error',
            ]);
        });
    }
};
