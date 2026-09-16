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
        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('status');
            $table->timestampTz('joined_at');
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
            $table->index('user_id');
        });

        DB::statement("ALTER TABLE organization_members ADD CONSTRAINT organization_members_role_check CHECK (role IN ('owner', 'staff'))");
        DB::statement("ALTER TABLE organization_members ADD CONSTRAINT organization_members_status_check CHECK (status IN ('active', 'suspended'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_members');
    }
};
