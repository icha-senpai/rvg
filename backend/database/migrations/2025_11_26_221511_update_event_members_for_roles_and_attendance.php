<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_members', function (Blueprint $table) {

            // 1. Add new event-specific role relationship
            $table->foreignId('event_role_id')
                ->nullable()
                ->after('user_id')
                ->constrained('event_roles')
                ->onDelete('set null');

            // 2. Add check-in timestamp
            $table->timestamp('checked_in_at')
                ->nullable()
                ->after('stats');
        });

        //////////////////////////////////////////
        // 3. Replace attendance_status enum safely
        //////////////////////////////////////////

        // Drop old check constraint (Postgres only)
        DB::statement("
            ALTER TABLE event_members 
            DROP CONSTRAINT IF EXISTS event_members_attendance_status_check;
        ");

        // Drop and recreate attendance_status as TEXT
        DB::statement("
            ALTER TABLE event_members 
            ALTER COLUMN attendance_status TYPE TEXT;
        ");

        // Add upgraded check constraint
        DB::statement("
            ALTER TABLE event_members
            ADD CONSTRAINT event_members_attendance_status_check
            CHECK (attendance_status IN (
                'signed_up', 
                'attended', 
                'missed', 
                'late', 
                'no_show'
            ));
        ");
    }

    public function down(): void
    {
        Schema::table('event_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_role_id');
            $table->dropColumn('checked_in_at');
        });

        // Restore old constraint
        DB::statement("
            ALTER TABLE event_members 
            DROP CONSTRAINT IF EXISTS event_members_attendance_status_check;
        ");

        DB::statement("
            ALTER TABLE event_members 
            ADD CONSTRAINT event_members_attendance_status_check
            CHECK (attendance_status IN ('signed_up', 'attended', 'missed'));
        ");
    }
};
