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
        Schema::table('events', function (Blueprint $table): void {
            $table->unsignedSmallInteger('certificate_min_attendance_pct')->nullable();
        });
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_cert_pct_check CHECK (certificate_min_attendance_pct IS NULL OR (certificate_min_attendance_pct BETWEEN 1 AND 100))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE events DROP CONSTRAINT IF EXISTS events_cert_pct_check');
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('certificate_min_attendance_pct');
        });
    }
};
