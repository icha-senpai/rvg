<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('squadrons', function (Blueprint $table) {
            $table->string('motto')->nullable();
            $table->text('description')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('emblem_path')->nullable();
            $table->boolean('recruiting')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('squadrons', function (Blueprint $table) {
            $table->dropColumn([
                'motto',
                'description',
                'primary_color',
                'secondary_color',
                'emblem_path',
                'recruiting',
            ]);
        });
    }
};
