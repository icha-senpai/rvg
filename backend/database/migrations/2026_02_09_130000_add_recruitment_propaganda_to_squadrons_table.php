<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('squadrons', function (Blueprint $table) {
            $table->text('recruitment_propaganda')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('squadrons', function (Blueprint $table) {
            $table->dropColumn('recruitment_propaganda');
        });
    }
};
