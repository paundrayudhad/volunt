<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->string('priority', 10)->default('medium');
            $table->string('location', 255);
            $table->text('description');
            $table->string('attachment_path', 255)->nullable();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->foreignId('lost_found_item_id')->nullable()->constrained('lost_found_items')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'priority']);
        });
        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_category_check CHECK (category IN ('medical','security','crowd','technical','lost_found','other'))");
        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_priority_check CHECK (priority IN ('low','medium','high','critical'))");
        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_status_check CHECK (status IN ('open','assigned','in_progress','resolved','closed'))");

        Schema::create('incident_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_status_histories');
        Schema::dropIfExists('incidents');
    }
};
