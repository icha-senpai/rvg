<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');              // Human name, e.g. "Director"
            $table->string('slug')->unique();    // Machine name, e.g. "director"
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false); // true for core roles like Director
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
