<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_stand_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'location_id']);
        });

        Schema::create('product_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('product_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('channel', 20);
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2);
            $table->string('truck_plate', 30)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->dateTime('sold_at');
            $table->timestamps();
            $table->index(['channel', 'sold_at']);
            $table->index(['product_id', 'sold_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sales');
        Schema::dropIfExists('product_allocations');
        Schema::dropIfExists('product_stand_stocks');
    }
};
