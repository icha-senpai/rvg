<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('operations', 'operation_kind') && ! Schema::hasColumn('operations', 'operation_type')) {
            Schema::table('operations', function (Blueprint $table) {
                $table->renameColumn('operation_kind', 'operation_type');
            });
        }

        if (Schema::hasColumn('operations', 'type') && ! Schema::hasColumn('operations', 'gameplay_type')) {
            Schema::table('operations', function (Blueprint $table) {
                $table->renameColumn('type', 'gameplay_type');
            });
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

        if (Schema::hasColumn('operations', 'operation_type') && ! Schema::hasColumn('operations', 'operation_kind')) {
            Schema::table('operations', function (Blueprint $table) {
                $table->renameColumn('operation_type', 'operation_kind');
            });
        }

        if (Schema::hasColumn('operations', 'gameplay_type') && ! Schema::hasColumn('operations', 'type')) {
            Schema::table('operations', function (Blueprint $table) {
                $table->renameColumn('gameplay_type', 'type');
            });
        }
    }
};
