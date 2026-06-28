<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producers', function (Blueprint $table) {
            $table->decimal('income_value', 12, 2)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('producers', function (Blueprint $table) {
            $table->dropColumn('income_value');
        });
    }
};
