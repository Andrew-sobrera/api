<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Mapa principal');
            $table->string('floor_plan_url')->nullable();
            $table->unsignedInteger('width')->default(800);
            $table->unsignedInteger('height')->default(600);
            $table->string('stage_label')->default('PALCO');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_maps');
    }
};
