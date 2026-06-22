<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_id')->constrained('event_sectors')->cascadeOnDelete();
            $table->string('name', 10);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('pos_x', 8, 2)->default(0);
            $table->decimal('pos_y', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['sector_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_rows');
    }
};
