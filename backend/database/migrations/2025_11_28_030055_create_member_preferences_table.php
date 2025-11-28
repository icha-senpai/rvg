<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_preferences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Controlled status for logic
            $table->string('status', 32)->nullable(); // active | inactive | loa

            // LOA date if applicable
            $table->date('loa_until')->nullable();

            // Freeform arrays
            $table->json('preferred_times')->nullable(); // array of strings
            $table->json('focus')->nullable();           // array of strings
            $table->json('roles')->nullable();           // array of strings

            // Extra freeform notes
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_preferences');
    }
};
