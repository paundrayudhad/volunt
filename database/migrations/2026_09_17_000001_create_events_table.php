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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('venue')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone')->default('Asia/Jakarta');
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->timestampTz('registration_start_at')->nullable();
            $table->timestampTz('registration_end_at')->nullable();
            $table->string('status')->default('draft');
            $table->integer('capacity')->nullable();
            $table->jsonb('contact')->nullable();
            $table->jsonb('branding')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->text('terms')->nullable();
            $table->text('privacy_notice')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'status']);
            $table->index(['status', 'start_at']);
        });

        DB::statement("ALTER TABLE events ADD CONSTRAINT events_status_check CHECK (status IN ('draft','published','registration_open','registration_closed','ongoing','completed','archived','cancelled'))");
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_dates_check CHECK (end_at > start_at)');
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_registration_window_check CHECK (registration_start_at IS NULL OR registration_end_at IS NULL OR registration_start_at < registration_end_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
