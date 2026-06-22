<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('cart_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('ticket_reservations', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('ticket_reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreignId('cart_id')->nullable()->after('order_id')->constrained()->cascadeOnDelete();
            $table->index(['cart_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_reservations', function (Blueprint $table) {
            $table->dropForeign(['cart_id']);
            $table->dropIndex(['cart_id', 'status']);
            $table->dropColumn('cart_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cart_id');
        });
    }
};
