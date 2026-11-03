<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_stock_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity_added');
            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('quantity_after');
            $table->string('source', 20);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });

        $now = now();
        DB::table('products')->where('quantity', '>', 0)->orderBy('id')->chunkById(200, function ($products) use ($now) {
            foreach ($products as $product) {
                DB::table('product_stock_entries')->insert([
                    'product_id' => $product->id,
                    'created_by_user_id' => null,
                    'quantity_added' => $product->quantity,
                    'quantity_before' => 0,
                    'quantity_after' => $product->quantity,
                    'source' => 'opening',
                    'note' => 'Saldo existente antes do histórico de entradas',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_entries');
    }
};
