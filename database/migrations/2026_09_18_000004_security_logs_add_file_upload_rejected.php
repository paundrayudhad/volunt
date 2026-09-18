<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE security_logs DROP CONSTRAINT IF EXISTS security_logs_type_check');
        DB::statement("ALTER TABLE security_logs ADD CONSTRAINT security_logs_type_check CHECK (type IN ('failed_login', 'account_locked', 'forbidden_access', 'rate_limit_exceeded', 'csrf_violation', 'suspicious_request', 'file_upload_rejected'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE security_logs DROP CONSTRAINT IF EXISTS security_logs_type_check');
        DB::statement("ALTER TABLE security_logs ADD CONSTRAINT security_logs_type_check CHECK (type IN ('failed_login', 'account_locked', 'forbidden_access', 'rate_limit_exceeded', 'csrf_violation', 'suspicious_request'))");
    }
};
