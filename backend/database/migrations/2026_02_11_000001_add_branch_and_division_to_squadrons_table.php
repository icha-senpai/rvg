<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('squadrons', function (Blueprint $table) {
            $table->string('branch')->nullable();
            $table->string('division')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('squadrons', function (Blueprint $table) {
            $table->dropColumn(['branch', 'division']);
        });
    }
};
