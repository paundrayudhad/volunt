<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained('event_divisions')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('event_roles')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('event_shifts')->cascadeOnDelete();
            $table->string('location')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('assigned');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['event_id', 'status']);
            $table->index(['user_id', 'event_id']);
            $table->index(['shift_id']);
        });

        DB::statement("ALTER TABLE assignments ADD CONSTRAINT assignments_status_check CHECK (status IN ('assigned','reassigned','confirmed','completed','cancelled'))");

        Schema::create('assignment_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->index(['assignment_id']);
        });

        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('event_shifts')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('checked_in_at')->nullable();
            $table->timestampTz('checked_out_at')->nullable();
            $table->string('method', 16)->default('qr');
            $table->string('status', 16)->default('present');
            $table->uuid('idempotency_key')->unique();
            $table->timestamps();
            $table->unique(['assignment_id', 'shift_id']);
            $table->index(['event_id', 'checked_in_at']);
            $table->index(['user_id', 'event_id']);
        });

        DB::statement("ALTER TABLE attendances ADD CONSTRAINT attendances_method_check CHECK (method IN ('qr','manual'))");
        DB::statement("ALTER TABLE attendances ADD CONSTRAINT attendances_status_check CHECK (status IN ('present','late','absent'))");

        Schema::create('attendance_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->string('action', 16);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->index(['attendance_id']);
        });

        DB::statement("ALTER TABLE attendance_logs ADD CONSTRAINT attendance_logs_action_check CHECK (action IN ('check_in','check_out','void'))");

        Schema::create('qr_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['assignment_id']);
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_type', 16)->default('event');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('title');
            $table->text('body');
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'published_at']);
        });

        DB::statement("ALTER TABLE announcements ADD CONSTRAINT announcements_target_type_check CHECK (target_type IN ('event','division','role','shift','individual'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('qr_tokens');
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('assignment_histories');
        Schema::dropIfExists('assignments');
    }
};
