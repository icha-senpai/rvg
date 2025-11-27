<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Visual / identity stuff
            $table->string('icon')->nullable()->after('type');        // e.g. emoji or icon key
            $table->string('image_url')->nullable()->after('icon');   // banner or thumbnail

            // Difficulty & strictness
            $table->enum('difficulty', ['low', 'medium', 'high'])
                ->default('medium')
                ->after('image_url');

            $table->enum('operation_strictness', ['casual', 'normal', 'strict', 'roleplay'])
                ->default('normal')
                ->after('difficulty');

            // RSVP and notes
            $table->timestamp('rsvp_deadline')->nullable()->after('end_time');
            $table->text('notes')->nullable()->after('rsvp_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'icon',
                'image_url',
                'difficulty',
                'operation_strictness',
                'rsvp_deadline',
                'notes',
            ]);
        });
    }
};
