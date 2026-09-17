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
        Schema::create('event_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained('event_divisions')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('event_roles')->cascadeOnDelete();
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->string('location')->nullable();
            $table->integer('capacity')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['event_id', 'start_at']);
            $table->index(['role_id', 'start_at']);
        });

        DB::statement('ALTER TABLE event_shifts ADD CONSTRAINT event_shifts_dates_check CHECK (end_at > start_at)');
        DB::statement('ALTER TABLE event_shifts ADD CONSTRAINT event_shifts_capacity_check CHECK (capacity IS NULL OR capacity >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_shifts');
    }
};
