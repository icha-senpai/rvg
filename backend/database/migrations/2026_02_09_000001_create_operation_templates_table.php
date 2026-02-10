<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operation_templates', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('scope');

            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('squadron_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->json('payload');

            $table->timestamps();

            $table->index(['scope', 'owner_user_id']);
            $table->index(['scope', 'squadron_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_templates');
    }
};
