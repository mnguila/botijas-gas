<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_damage_events', function (Blueprint $table) {
            $table->foreignId('received_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('supplier_name', 150)->nullable();
            $table->string('reference', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stock_damage_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_location_id');
            $table->dropColumn(['supplier_name', 'reference']);
        });
    }
};
