<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producer_id')->nullable()->constrained('producers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::table('venue_maps', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->after('event_id')->constrained()->nullOnDelete();
            $table->decimal('floor_plan_opacity', 3, 2)->default(1)->after('floor_plan_url');
            $table->decimal('floor_plan_scale_x', 8, 4)->default(1)->after('floor_plan_opacity');
            $table->decimal('floor_plan_scale_y', 8, 4)->default(1)->after('floor_plan_scale_x');
            $table->boolean('floor_plan_locked')->default(false)->after('floor_plan_scale_y');
            $table->boolean('floor_plan_visible')->default(true)->after('floor_plan_locked');
        });

        Schema::create('map_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_map_id')->constrained()->cascadeOnDelete();
            $table->string('element_key', 64);
            $table->string('type', 32);
            $table->string('label')->nullable();
            $table->decimal('pos_x', 10, 2)->default(0);
            $table->decimal('pos_y', 10, 2)->default(0);
            $table->integer('rotation')->default(0);
            $table->decimal('scale', 8, 4)->default(1);
            $table->unsignedInteger('width')->default(56);
            $table->unsignedInteger('height')->default(56);
            $table->json('props')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['venue_map_id', 'element_key']);
        });

        Schema::create('venue_map_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_map_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('label');
            $table->json('snapshot');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['venue_map_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_map_versions');
        Schema::dropIfExists('map_elements');
        Schema::table('venue_maps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_id');
            $table->dropColumn([
                'floor_plan_opacity',
                'floor_plan_scale_x',
                'floor_plan_scale_y',
                'floor_plan_locked',
                'floor_plan_visible',
            ]);
        });
        Schema::dropIfExists('venues');
    }
};
