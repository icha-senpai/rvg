<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('promoter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_rank', 64);
            $table->string('to_rank', 64);
            $table->string('discord_branch_role_id')->nullable();
            $table->string('discord_dm_message_id')->nullable();
            $table->string('discord_quarter_message_id')->nullable();
            $table->string('state', 32)->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_offers');
    }
};
