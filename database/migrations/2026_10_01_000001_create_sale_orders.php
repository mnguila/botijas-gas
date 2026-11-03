<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_orders', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('truck_plate', 30)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->dateTime('sold_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['channel', 'sold_at']);
        });

        Schema::table('product_sales', function (Blueprint $table) {
            $table->foreignId('sale_order_id')->nullable()->constrained('sale_orders')->restrictOnDelete();
        });

        // Cada venda antiga passa a ser uma venda de um item, sem alterar o histórico.
        DB::table('product_sales')->orderBy('id')->chunkById(100, function ($sales) {
            foreach ($sales as $sale) {
                $orderId = DB::table('sale_orders')->insertGetId([
                    'channel' => $sale->channel,
                    'location_id' => $sale->location_id,
                    'vehicle_location_id' => $sale->vehicle_location_id,
                    'truck_plate' => $sale->truck_plate,
                    'driver_name' => $sale->driver_name,
                    'created_by_user_id' => $sale->created_by_user_id,
                    'note' => $sale->note,
                    'sold_at' => $sale->sold_at,
                    'cancelled_at' => $sale->cancelled_at,
                    'cancelled_by_user_id' => $sale->cancelled_by_user_id,
                    'created_at' => $sale->created_at,
                    'updated_at' => $sale->updated_at,
                ]);
                DB::table('product_sales')->where('id', $sale->id)->update(['sale_order_id' => $orderId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_sales', fn (Blueprint $table) => $table->dropConstrainedForeignId('sale_order_id'));
        Schema::dropIfExists('sale_orders');
    }
};
