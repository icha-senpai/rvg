<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Profile / identity
            $table->text('bio')->nullable();
            $table->string('timezone', 64)->nullable();

            // Arrays / JSON fields
            $table->json('preferred_roles')->nullable();
            $table->json('notification_settings')->nullable();
            $table->json('personal_tags')->nullable();

            // Availability / LOA
            $table->string('availability_status', 32)->nullable(); // e.g. active, limited, loa
            $table->text('loa_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'bio',
                'timezone',
                'preferred_roles',
                'notification_settings',
                'personal_tags',
                'availability_status',
                'loa_note',
            ]);
        });
    }
};
