<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_lat', 10, 7)->nullable();
            $table->decimal('old_lng', 10, 7)->nullable();
            $table->decimal('new_lat', 10, 7)->nullable();
            $table->decimal('new_lng', 10, 7)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_audits');
    }
};
