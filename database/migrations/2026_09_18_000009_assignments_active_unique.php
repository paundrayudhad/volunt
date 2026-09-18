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
        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropUnique(['registration_id']);
        });
        DB::statement("CREATE UNIQUE INDEX assignments_registration_active_uniq ON assignments (registration_id) WHERE status IN ('assigned','reassigned','confirmed')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS assignments_registration_active_uniq');
        Schema::table('assignments', function (Blueprint $table): void {
            $table->unique('registration_id');
        });
    }
};
