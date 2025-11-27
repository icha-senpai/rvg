<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // If the pivot already exists, drop it cleanly first.
        if (Schema::hasTable('permission_role')) {
            Schema::drop('permission_role');
        }

        Schema::create('permission_role', function (Blueprint $table) {
            // Foreign keys using Laravel's proper shortcuts
            $table->foreignId('role_id')
                  ->constrained('roles')
                  ->cascadeOnDelete();

            $table->foreignId('permission_id')
                  ->constrained('permissions')
                  ->cascadeOnDelete();

            // Composite primary key ensures proper sync() behavior
            $table->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
    }
};
