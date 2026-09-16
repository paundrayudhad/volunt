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
        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->string('token_hash')->unique();
            $table->timestampTz('expires_at')->default(DB::raw("(now() + interval '7 days')"));
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('declined_at')->nullable();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE organization_invitations ADD CONSTRAINT organization_invitations_role_check CHECK (role IN ('staff'))");
        DB::statement('CREATE UNIQUE INDEX organization_invitations_pending_uidx ON organization_invitations (organization_id, email) WHERE accepted_at IS NULL AND declined_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS organization_invitations_pending_uidx');
        Schema::dropIfExists('organization_invitations');
    }
};
