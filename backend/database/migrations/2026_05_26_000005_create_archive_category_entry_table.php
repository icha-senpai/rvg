<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_category_entry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_entry_id')->constrained('archive_entries')->cascadeOnDelete();
            $table->foreignId('archive_category_id')->constrained('archive_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['archive_entry_id', 'archive_category_id'], 'archive_category_entry_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_category_entry');
    }
};
