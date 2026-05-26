<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_entry_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_entry_id')->constrained('archive_entries')->cascadeOnDelete();
            $table->foreignId('archive_tag_id')->constrained('archive_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['archive_entry_id', 'archive_tag_id'], 'archive_entry_tag_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_entry_tag');
    }
};
