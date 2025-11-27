<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the existing pivot table cleanly
        Schema::dropIfExists('permission_role');

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')
                  ->constrained('roles')
                  ->cascadeOnDelete();

            $table->foreignId('permission_id')
                  ->constrained('permissions')
                  ->cascadeOnDelete();

            // Required for Laravel's sync() to behave correctly
            $table->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
    }
};
