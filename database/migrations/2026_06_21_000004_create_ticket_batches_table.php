<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_event_id')->constrained('ticket_events')->cascadeOnDelete();
            $table->foreignId('sector_id')->nullable()->constrained('event_sectors')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('sold_quantity')->default(0);
            $table->unsignedInteger('price');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['ticket_event_id', 'status']);
            $table->index(['sector_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_batches');
    }
};
