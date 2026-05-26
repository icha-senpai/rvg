<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archive_topics', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('archive_entries', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('archive_categories', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('archive_tags', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('archive_tags', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('archive_categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('archive_entries', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('archive_topics', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
