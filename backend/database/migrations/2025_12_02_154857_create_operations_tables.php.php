<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('squadron_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();

            $table->string('visibility')->default('open'); // open/squadron/private
            $table->string('operation_kind')->default('event'); // 'event' or 'mission'
            $table->string('type')->nullable(); // operation subtype

            $table->string('difficulty')->nullable();
            $table->string('operation_strictness')->nullable();
            $table->string('icon')->nullable();
            $table->string('image_url')->nullable();

            $table->dateTime('rsvp_deadline')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->text('cancellation_reason')->nullable();

            $table->json('slots')->nullable(); // mission-style slots

            $table->timestamps();
        });

        Schema::create('operation_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')->constrained()->cascadeOnDelete();
            $table->string('role_name');
            $table->string('role_display_name')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('min_required')->default(0);
            $table->text('description')->nullable();
            $table->json('requirements')->nullable();
            $table->timestamps();
        });

        Schema::create('operation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('operation_role_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slot')->nullable();
            $table->string('attendance_status')->default('signed_up');
            $table->text('notes')->nullable();
            $table->json('stats')->nullable();

            $table->timestamps();

            $table->unique(['operation_id', 'user_id']); // prevent dup joins
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_participants');
        Schema::dropIfExists('operation_roles');
        Schema::dropIfExists('operations');
    }
};
