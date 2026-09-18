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
        Schema::table('event_shifts', function (Blueprint $table): void {
            $table->integer('filled_count')->default(0);
        });

        DB::statement('ALTER TABLE event_shifts ADD CONSTRAINT event_shifts_filled_count_check CHECK (filled_count >= 0)');
        DB::statement('ALTER TABLE event_shifts ADD CONSTRAINT event_shifts_filled_within_capacity_check CHECK (capacity IS NULL OR filled_count <= capacity)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE event_shifts DROP CONSTRAINT IF EXISTS event_shifts_filled_within_capacity_check');
        DB::statement('ALTER TABLE event_shifts DROP CONSTRAINT IF EXISTS event_shifts_filled_count_check');

        Schema::table('event_shifts', function (Blueprint $table): void {
            $table->dropColumn('filled_count');
        });
    }
};
