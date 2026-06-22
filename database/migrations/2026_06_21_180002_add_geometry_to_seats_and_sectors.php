<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_sectors', function (Blueprint $table) {
            $table->string('color', 7)->default('#003366')->after('description');
            $table->string('category')->nullable()->after('color');
            $table->decimal('pos_x', 8, 2)->default(0)->after('sort_order');
            $table->decimal('pos_y', 8, 2)->default(0)->after('pos_x');
            $table->boolean('map_visible')->default(true)->after('pos_y');
        });

        Schema::table('seats', function (Blueprint $table) {
            $table->foreignId('seat_row_id')->nullable()->after('sector_id')->constrained('seat_rows')->nullOnDelete();
            $table->decimal('pos_x', 8, 2)->default(0)->after('label');
            $table->decimal('pos_y', 8, 2)->default(0)->after('pos_x');
            $table->unsignedSmallInteger('rotation')->default(0)->after('pos_y');
            $table->unsignedSmallInteger('width')->default(28)->after('rotation');
            $table->unsignedSmallInteger('height')->default(28)->after('width');
            $table->string('seat_type', 20)->default('standard')->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seat_row_id');
            $table->dropColumn(['pos_x', 'pos_y', 'rotation', 'width', 'height', 'seat_type']);
        });

        Schema::table('event_sectors', function (Blueprint $table) {
            $table->dropColumn(['color', 'category', 'pos_x', 'pos_y', 'map_visible']);
        });
    }
};
