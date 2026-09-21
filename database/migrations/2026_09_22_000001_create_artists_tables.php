<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('genre', 100)->nullable();
            $table->string('stage', 100)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->integer('performance_order')->nullable();
            $table->string('contact_name', 255)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->text('rider_text')->nullable();
            $table->boolean('rider_fulfilled')->default(false);
            $table->string('status', 20)->default('scheduled');
            $table->string('attendance', 20)->default('expected');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['event_id', 'scheduled_at']);
            $table->index(['event_id', 'status']);
        });
        DB::statement("ALTER TABLE artists ADD CONSTRAINT artists_status_check CHECK (status IN ('scheduled','soundcheck','performing','done','cancelled'))");
        DB::statement("ALTER TABLE artists ADD CONSTRAINT artists_attendance_check CHECK (attendance IN ('expected','arrived','no_show'))");

        Schema::create('artist_liaisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('user_id');
        });
        DB::statement('CREATE UNIQUE INDEX artist_liaisons_aktif_unique ON artist_liaisons (artist_id, user_id) WHERE deleted_at IS NULL');

        Schema::create('artist_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->string('from_attendance', 20)->nullable();
            $table->string('to_attendance', 20)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('artist_id');
        });

        Schema::create('artist_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->index('artist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_notes');
        Schema::dropIfExists('artist_status_histories');
        Schema::dropIfExists('artist_liaisons');
        Schema::dropIfExists('artists');
    }
};
