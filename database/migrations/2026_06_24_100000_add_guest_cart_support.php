<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        foreach (DB::table('carts')->whereNull('uuid')->pluck('id') as $cartId) {
            DB::table('carts')->where('id', $cartId)->update(['uuid' => (string) Str::uuid()]);
        }

        Schema::table('carts', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_unique_line');
            $table->unique(
                ['cart_id', 'event_ticket_id', 'batch_id', 'seat_id'],
                'cart_items_cart_unique_line'
            );
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_cart_unique_line');
            $table->unique(
                ['user_id', 'event_ticket_id', 'batch_id', 'seat_id'],
                'cart_items_unique_line'
            );
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropColumn('uuid');
        });
    }
};
