<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('operations')
            ->where('operation_kind', 'mission')
            ->update(['operation_kind' => 'operation']);

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE operations ALTER COLUMN operation_kind SET DEFAULT 'operation'");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE operations ALTER COLUMN operation_kind SET DEFAULT 'event'");
        }
    }
};
