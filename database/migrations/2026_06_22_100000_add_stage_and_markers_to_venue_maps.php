<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_maps', function (Blueprint $table) {
            $table->unsignedInteger('stage_x')->nullable()->after('stage_label');
            $table->unsignedInteger('stage_y')->nullable()->after('stage_x');
            $table->unsignedInteger('stage_width')->default(280)->after('stage_y');
            $table->unsignedInteger('stage_height')->default(36)->after('stage_width');
            $table->json('markers')->nullable()->after('stage_height');
        });
    }

    public function down(): void
    {
        Schema::table('venue_maps', function (Blueprint $table) {
            $table->dropColumn(['stage_x', 'stage_y', 'stage_width', 'stage_height', 'markers']);
        });
    }
};
