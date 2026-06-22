<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_reservations', function (Blueprint $table) {
            $table->foreignId('seat_id')->nullable()->after('order_id')->constrained('seats')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->after('seat_id')->constrained('ticket_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seat_id');
            $table->dropConstrainedForeignId('batch_id');
        });
    }
};
