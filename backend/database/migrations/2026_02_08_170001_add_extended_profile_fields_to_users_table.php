<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('favorite_ships')->nullable();
            $table->json('favorite_guns')->nullable();

            $table->string('primary_role', 64)->nullable();
            $table->string('secondary_role', 64)->nullable();

            $table->json('experience_ratings')->nullable();

            $table->string('preferred_gameplay_style', 64)->nullable();
            $table->string('callsign', 64)->nullable();
            $table->string('typical_op_commitment', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'favorite_ships',
                'favorite_guns',
                'primary_role',
                'secondary_role',
                'experience_ratings',
                'preferred_gameplay_style',
                'callsign',
                'typical_op_commitment',
            ]);
        });
    }
};
