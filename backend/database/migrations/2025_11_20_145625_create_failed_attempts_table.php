<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('failed_attempts', function (Blueprint $table) {
            $table->id();

            // What was being attempted (login, discord, rsi, etc.)
            $table->string('action');

            // IP of the client
            $table->string('ip_address')->nullable();

            // Optional: user ID (if known)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // How many attempts
            $table->integer('attempts')->default(0);

            // Lockout timestamp
            $table->timestamp('locked_until')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_attempts');
    }
};
