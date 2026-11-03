<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->unsignedSmallInteger('weight_kg');
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('reseller_price', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['brand', 'weight_kg']);
        });
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('kind');
            $table->string('plate', 30)->nullable()->unique();
            $table->boolean('is_sales_point')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('locations');
        Schema::dropIfExists('products');
    }
};
