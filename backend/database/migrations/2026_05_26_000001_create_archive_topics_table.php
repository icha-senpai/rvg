<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_topics', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category_label')->nullable();
            $table->string('card_image_path')->nullable();
            $table->string('banner_image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->unsignedTinyInteger('minimum_rank_level')->nullable()->index();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_published', 'minimum_rank_level', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_topics');
    }
};
