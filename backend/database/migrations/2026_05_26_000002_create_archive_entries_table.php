<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_topic_id')->constrained('archive_topics')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('banner_image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->unsignedTinyInteger('minimum_rank_level')->nullable()->index();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['archive_topic_id', 'slug']);
            $table->index(['archive_topic_id', 'is_published', 'minimum_rank_level', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_entries');
    }
};
