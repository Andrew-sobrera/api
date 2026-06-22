<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'label']);
            $table->unique(['sector_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->dropUnique(['sector_id', 'label']);
            $table->unique(['event_id', 'label']);
        });
    }
};
