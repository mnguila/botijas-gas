<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->unsignedInteger('damaged_quantity')->default(0));
        Schema::table('product_stand_stocks', fn (Blueprint $table) => $table->unsignedInteger('damaged_quantity')->default(0));
        Schema::table('product_stock_entries', fn (Blueprint $table) => $table->unsignedInteger('damaged_quantity_added')->default(0));
        Schema::table('product_sales', fn (Blueprint $table) => $table->unsignedInteger('refunded_quantity')->default(0));

        Schema::create('stock_damage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_sale_id')->nullable()->constrained('product_sales')->restrictOnDelete();
            $table->string('kind', 24);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('refund_cents')->default(0);
            $table->string('reason', 500);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
            $table->index(['kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_damage_events');
        Schema::table('product_sales', fn (Blueprint $table) => $table->dropColumn('refunded_quantity'));
        Schema::table('product_stock_entries', fn (Blueprint $table) => $table->dropColumn('damaged_quantity_added'));
        Schema::table('product_stand_stocks', fn (Blueprint $table) => $table->dropColumn('damaged_quantity'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('damaged_quantity'));
    }
};
