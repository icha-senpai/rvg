<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();

            $table->enum('type', [
                'cargo',
                'escort',
                'mining',
                'bounty',
                'recon',
                'training',
                'multi-role',
                'other'
            ])->default('other');

            $table->enum('status', [
                'scheduled',
                'active',
                'completed',
                'cancelled'
            ])->default('scheduled');

            $table->json('slots')->nullable();  

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
