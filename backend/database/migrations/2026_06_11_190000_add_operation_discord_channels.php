<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_discord_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('discord_channel_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('operation_participants', function (Blueprint $table) {
            $table->foreignId('operation_discord_channel_id')
                ->nullable()
                ->after('operation_role_id')
                ->constrained('operation_discord_channels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('operation_participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operation_discord_channel_id');
        });

        Schema::dropIfExists('operation_discord_channels');
    }
};
