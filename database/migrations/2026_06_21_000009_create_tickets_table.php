<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_ticket_id')->constrained('ticket_events')->cascadeOnDelete();
            $table->foreignId('sector_id')->nullable()->constrained('event_sectors')->nullOnDelete();
            $table->foreignId('seat_id')->nullable()->constrained('seats')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('ticket_batches')->nullOnDelete();
            $table->string('buyer_name');
            $table->string('buyer_email');
            $table->string('qr_code_url')->nullable();
            $table->string('hash')->unique();
            $table->string('status', 20)->default('generated');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
