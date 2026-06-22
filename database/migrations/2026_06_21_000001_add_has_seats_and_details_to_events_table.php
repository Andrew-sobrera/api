<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('has_seats')->default(false)->after('ticket_type');
            $table->text('description')->nullable()->after('name');
            $table->string('location')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['has_seats', 'description', 'location']);
        });
    }
};
