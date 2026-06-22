<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_events', function (Blueprint $table) {
            $table->foreignId('sector_id')->nullable()->after('event_id')->constrained('event_sectors')->nullOnDelete();
            $table->text('description')->nullable()->after('name');
            $table->string('status', 20)->default('active')->after('quantity');
            $table->json('sale_rules')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sector_id');
            $table->dropColumn(['description', 'status', 'sale_rules']);
        });
    }
};
