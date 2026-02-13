<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            if (Schema::hasColumn('operations', 'operation_kind') && ! Schema::hasColumn('operations', 'operation_type')) {
                DB::statement('ALTER TABLE operations RENAME COLUMN operation_kind TO operation_type');
            }

            if (Schema::hasColumn('operations', 'type') && ! Schema::hasColumn('operations', 'gameplay_type')) {
                DB::statement('ALTER TABLE operations RENAME COLUMN type TO gameplay_type');
            }
        }

        if (Schema::hasTable('operation_templates') && Schema::hasColumn('operation_templates', 'payload')) {
            DB::table('operation_templates')
                ->orderBy('id')
                ->chunkById(100, function ($rows) {
                    foreach ($rows as $row) {
                        $payload = json_decode($row->payload ?? '', true);

                        if (! is_array($payload)) {
                            continue;
                        }

                        $changed = false;

                        if (! array_key_exists('operation_type', $payload) && array_key_exists('operation_kind', $payload)) {
                            $payload['operation_type'] = $payload['operation_kind'];
                            unset($payload['operation_kind']);
                            $changed = true;
                        }

                        if (! array_key_exists('gameplay_type', $payload) && array_key_exists('type', $payload)) {
                            $payload['gameplay_type'] = $payload['type'];
                            unset($payload['type']);
                            $changed = true;
                        }

                        if ($changed) {
                            DB::table('operation_templates')
                                ->where('id', $row->id)
                                ->update([
                                    'payload' => json_encode($payload),
                                ]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('operation_templates') && Schema::hasColumn('operation_templates', 'payload')) {
            DB::table('operation_templates')
                ->orderBy('id')
                ->chunkById(100, function ($rows) {
                    foreach ($rows as $row) {
                        $payload = json_decode($row->payload ?? '', true);

                        if (! is_array($payload)) {
                            continue;
                        }

                        $changed = false;

                        if (! array_key_exists('operation_kind', $payload) && array_key_exists('operation_type', $payload)) {
                            $payload['operation_kind'] = $payload['operation_type'];
                            unset($payload['operation_type']);
                            $changed = true;
                        }

                        if (! array_key_exists('type', $payload) && array_key_exists('gameplay_type', $payload)) {
                            $payload['type'] = $payload['gameplay_type'];
                            unset($payload['gameplay_type']);
                            $changed = true;
                        }

                        if ($changed) {
                            DB::table('operation_templates')
                                ->where('id', $row->id)
                                ->update([
                                    'payload' => json_encode($payload),
                                ]);
                        }
                    }
                });
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            if (Schema::hasColumn('operations', 'operation_type') && ! Schema::hasColumn('operations', 'operation_kind')) {
                DB::statement('ALTER TABLE operations RENAME COLUMN operation_type TO operation_kind');
            }

            if (Schema::hasColumn('operations', 'gameplay_type') && ! Schema::hasColumn('operations', 'type')) {
                DB::statement('ALTER TABLE operations RENAME COLUMN gameplay_type TO type');
            }
        }
    }
};
