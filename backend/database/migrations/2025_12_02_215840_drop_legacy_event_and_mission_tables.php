<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop legacy mission tables first (to avoid FK weirdness)
        if (Schema::hasTable('mission_members')) {
            Schema::drop('mission_members');
        }

        if (Schema::hasTable('missions')) {
            Schema::drop('missions');
        }

        // Drop legacy event tables
        if (Schema::hasTable('event_members')) {
            Schema::drop('event_members');
        }

        if (Schema::hasTable('event_roles')) {
            Schema::drop('event_roles');
        }

        if (Schema::hasTable('events')) {
            Schema::drop('events');
        }
    }

    public function down(): void
    {
        // No rollback here. You'd restore from backups instead.
    }
};
