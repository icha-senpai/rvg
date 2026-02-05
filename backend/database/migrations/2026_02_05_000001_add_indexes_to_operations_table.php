<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->index('status');
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['starts_at']);
        });
    }
};
