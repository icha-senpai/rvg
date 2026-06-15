<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_participants', function (Blueprint $table) {
            $table->string('runtime_status')->nullable()->after('attendance_status');
            $table->string('runtime_source')->nullable()->after('runtime_status');
            $table->timestamp('signed_off_at')->nullable()->after('runtime_source');
            $table->timestamp('synced_in_at')->nullable()->after('signed_off_at');
            $table->unsignedBigInteger('starting_auec')->nullable()->after('synced_in_at');
            $table->unsignedBigInteger('ending_auec')->nullable()->after('starting_auec');
            $table->text('runtime_notes')->nullable()->after('ending_auec');
        });

        Schema::create('operation_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('synced_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('synced_at');
            $table->json('source_channel_ids')->nullable();
            $table->json('present_user_ids')->nullable();
            $table->json('no_show_user_ids')->nullable();
            $table->json('walk_in_user_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_sync_runs');

        Schema::table('operation_participants', function (Blueprint $table) {
            $table->dropColumn([
                'runtime_status',
                'runtime_source',
                'signed_off_at',
                'synced_in_at',
                'starting_auec',
                'ending_auec',
                'runtime_notes',
            ]);
        });
    }
};
