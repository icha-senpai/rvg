<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('operations', 'deleted_at')) {
            return;
        }

        DB::table('operations')
            ->whereNotNull('deleted_at')
            ->update([
                'status' => 'canceled',
                'cancellation_reason' => DB::raw("COALESCE(cancellation_reason, 'Operation canceled (legacy soft delete).')"),
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

        Schema::table('operations', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('operations', 'deleted_at')) {
            return;
        }

        Schema::table('operations', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
