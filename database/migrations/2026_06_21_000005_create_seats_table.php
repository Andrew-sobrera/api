<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained('event_sectors')->cascadeOnDelete();
            $table->string('row_label', 10);
            $table->string('seat_number', 10);
            $table->string('label', 20);
            $table->string('status', 20)->default('available');
            $table->timestamps();

            $table->unique(['event_id', 'label']);
            $table->index(['sector_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
