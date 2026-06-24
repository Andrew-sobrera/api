<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address');
            $table->string('address_normalized')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('provider')->nullable();
            $table->string('geocoding_status')->default('pending');
            $table->timestamps();

            $table->index(['producer_id', 'name']);
        });

        Schema::create('geocode_cache', function (Blueprint $table) {
            $table->id();
            $table->string('address_normalized')->unique();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('provider');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocode_cache');
        Schema::dropIfExists('places');
    }
};
