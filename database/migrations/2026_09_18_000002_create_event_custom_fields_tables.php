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
        Schema::create('event_custom_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('type', 32);
            $table->boolean('required')->default(false);
            $table->string('placeholder')->nullable();
            $table->string('validation_rule', 512)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['event_id', 'sort_order']);
        });

        DB::statement("ALTER TABLE event_custom_fields ADD CONSTRAINT event_custom_fields_type_check CHECK (type IN ('text','textarea','email','phone','number','date','time','select','multi_select','radio','checkbox','url','file'))");

        Schema::create('event_custom_field_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_custom_field_id')->constrained('event_custom_fields')->cascadeOnDelete();
            $table->string('label');
            $table->string('value');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_custom_field_options');
        Schema::dropIfExists('event_custom_fields');
    }
};
