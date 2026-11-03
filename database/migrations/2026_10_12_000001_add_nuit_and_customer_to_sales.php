<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('nuit', 30)->nullable()->unique();
        });

        Schema::table('sale_orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('nuit'));
    }
};
