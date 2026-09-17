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
        Schema::create('event_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained('event_divisions')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('quota')->default(0);
            $table->integer('accepted_count')->default(0);
            $table->jsonb('requirements')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['event_id', 'division_id', 'name']);
        });

        DB::statement('ALTER TABLE event_roles ADD CONSTRAINT event_roles_quota_check CHECK (quota >= 0)');
        DB::statement('ALTER TABLE event_roles ADD CONSTRAINT event_roles_accepted_count_check CHECK (accepted_count >= 0)');
        DB::statement('ALTER TABLE event_roles ADD CONSTRAINT event_roles_accepted_within_quota_check CHECK (accepted_count <= quota)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_roles');
    }
};
