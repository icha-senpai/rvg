<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop old FK if it exists
        Schema::table('event_members', function (Blueprint $table) {
            $table->dropForeign(['event_role_id']);
        });

        // 2. Modify the column to nullable
        Schema::table('event_members', function (Blueprint $table) {
            $table->foreignId('event_role_id')
                ->nullable()
                ->change();
        });

        // 3. Re-add FK with nullOnDelete()
        Schema::table('event_members', function (Blueprint $table) {
            $table->foreign('event_role_id')
                ->references('id')->on('event_roles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Reverse: remove FK, make not-null, restore FK
        Schema::table('event_members', function (Blueprint $table) {
            $table->dropForeign(['event_role_id']);
        });

        Schema::table('event_members', function (Blueprint $table) {
            $table->foreignId('event_role_id')
                ->nullable(false)
                ->change();
        });

        Schema::table('event_members', function (Blueprint $table) {
            $table->foreign('event_role_id')
                ->references('id')->on('event_roles')
                ->cascadeOnDelete();
        });
    }
};
