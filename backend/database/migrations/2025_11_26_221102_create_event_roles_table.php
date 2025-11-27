<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_roles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->onDelete('cascade');

            $table->string('role_name');           // internal key like "hauler", "escort"
            $table->string('role_display_name');   // pretty human name

            $table->integer('capacity')->nullable();      // null = unlimited
            $table->integer('min_required')->default(0);  // min count for event viability

            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_roles');
    }
};
