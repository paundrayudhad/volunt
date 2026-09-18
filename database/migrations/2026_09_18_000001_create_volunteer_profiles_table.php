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
        Schema::create('volunteer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('phone', 32)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('education', 128)->nullable();
            $table->text('experience')->nullable();
            $table->jsonb('skills')->nullable();
            $table->string('portfolio_url', 512)->nullable();
            $table->jsonb('social_links')->nullable();
            $table->jsonb('availability')->nullable();
            $table->string('visibility', 32)->default('organizers_only');
            $table->date('date_of_birth')->nullable();
            $table->text('emergency_contact')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE volunteer_profiles ADD CONSTRAINT volunteer_profiles_visibility_check CHECK (visibility IN ('public','organizers_only','private'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volunteer_profiles');
    }
};
