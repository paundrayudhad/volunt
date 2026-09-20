<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lost_found_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('item_name', 255);
            $table->text('description')->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('location', 255)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('handler_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('claimant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'kind']);
        });
        DB::statement("ALTER TABLE lost_found_items ADD CONSTRAINT lost_found_items_kind_check CHECK (kind IN ('lost','found'))");
        DB::statement("ALTER TABLE lost_found_items ADD CONSTRAINT lost_found_items_status_check CHECK (status IN ('open','found','claimed','returned','closed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_found_items');
    }
};
