<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            // Who uploaded this file
            $table->foreignId('uploaded_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Usage context: avatar, squadron_emblem, operation_image, ship_image, site_asset
            $table->string('collection', 50)->index();

            // Original filename as uploaded by user (for display, not storage)
            $table->string('original_filename', 255);

            // Storage
            $table->string('disk', 20)->default('public');
            $table->string('path', 500);              // original file
            $table->string('thumbnail_path', 500)->nullable(); // ~200px variant
            $table->string('medium_path', 500)->nullable();    // ~800px variant

            // File metadata
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');         // bytes
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Accessibility / searchability
            $table->string('alt_text', 255)->nullable();

            // Polymorphic: what entity is this attached to?
            // Nullable — site_asset collection items may not be attached to anything
            $table->nullableMorphs('mediable');

            // Extensibility bucket for future needs (EXIF, tags, etc.)
            $table->json('meta')->nullable();

            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['collection', 'created_at']);
            $table->index(['uploaded_by', 'collection']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
