<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('event_roles')->nullOnDelete();
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        DB::statement("ALTER TABLE event_invitations ADD CONSTRAINT event_invitations_status_check CHECK (status IN ('pending','accepted','declined','expired','cancelled'))");
        DB::statement("CREATE UNIQUE INDEX event_invitations_pending_unique ON event_invitations (event_id, user_id) WHERE status = 'pending' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS event_invitations_pending_unique');
        Schema::dropIfExists('event_invitations');
    }
};
