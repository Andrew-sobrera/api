<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'producer_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('producer_id')->nullable()->constrained('producers');
            });
        }

        if (Schema::hasTable('events') && ! Schema::hasColumn('events', 'producer_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->foreignId('producer_id')->nullable()->constrained('producers');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events') && Schema::hasColumn('events', 'producer_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropConstrainedForeignId('producer_id');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'producer_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('producer_id');
            });
        }
    }
};
