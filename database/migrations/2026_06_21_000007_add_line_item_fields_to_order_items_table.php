<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('sector_id')->nullable()->after('event_ticket_id')->constrained('event_sectors')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->after('sector_id')->constrained('ticket_batches')->nullOnDelete();
            $table->foreignId('seat_id')->nullable()->after('batch_id')->constrained('seats')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sector_id');
            $table->dropConstrainedForeignId('batch_id');
            $table->dropConstrainedForeignId('seat_id');
        });
    }
};
