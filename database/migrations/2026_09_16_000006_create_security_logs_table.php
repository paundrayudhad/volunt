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
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->jsonb('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['type', 'created_at']);
            $table->index(['ip', 'created_at']);
        });

        DB::statement("ALTER TABLE security_logs ADD CONSTRAINT security_logs_type_check CHECK (type IN ('failed_login', 'account_locked', 'forbidden_access', 'rate_limit_exceeded', 'csrf_violation', 'suspicious_request'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};
