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
        Schema::create('registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('event_roles')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->timestamps();
            $table->index(['event_id', 'status']);
            $table->index(['role_id', 'status']);
            $table->index(['user_id']);
        });

        DB::statement("ALTER TABLE registrations ADD CONSTRAINT registrations_status_check CHECK (status IN ('pending','under_review','accepted','rejected','waitlisted','cancelled','withdrawn'))");
        DB::statement("CREATE UNIQUE INDEX registrations_user_event_active_uniq ON registrations (user_id, event_id) WHERE status IN ('pending','under_review','accepted','waitlisted')");

        Schema::create('registration_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_custom_field_id')->constrained('event_custom_fields')->cascadeOnDelete();
            $table->text('value_text')->nullable();
            $table->jsonb('value_jsonb')->nullable();
            $table->string('file_path', 512)->nullable();
            $table->timestamps();
            $table->unique(['registration_id', 'event_custom_field_id']);
        });

        Schema::create('registration_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->index(['registration_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_status_histories');
        Schema::dropIfExists('registration_answers');
        DB::statement('DROP INDEX IF EXISTS registrations_user_event_active_uniq');
        Schema::dropIfExists('registrations');
    }
};
