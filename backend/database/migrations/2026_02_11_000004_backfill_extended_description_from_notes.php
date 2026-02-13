<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('operations')) {
            return;
        }

        if (! Schema::hasColumn('operations', 'extended_description') || ! Schema::hasColumn('operations', 'notes')) {
            return;
        }

        DB::table('operations')
            ->whereNull('extended_description')
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->update([
                'extended_description' => DB::raw('notes'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('operations')) {
            return;
        }

        if (! Schema::hasColumn('operations', 'extended_description') || ! Schema::hasColumn('operations', 'notes')) {
            return;
        }

        DB::table('operations')
            ->whereNotNull('extended_description')
            ->whereNotNull('notes')
            ->whereColumn('extended_description', 'notes')
            ->update([
                'extended_description' => null,
            ]);
    }
};
