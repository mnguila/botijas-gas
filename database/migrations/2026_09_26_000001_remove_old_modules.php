<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('stock_movements');
        if (Schema::hasColumn('users', 'sales_location_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('sales_location_id'));
        }
        if (Schema::hasColumn('products', 'deleted_at')) {
            Schema::table('products', fn (Blueprint $table) => $table->dropColumn('deleted_at'));
        }
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereNotIn('key', array_keys(config('access.permissions')))->delete();
        }
    }

    public function down(): void
    {
        // A remoção dos módulos antigos e dos seus dados não é reversível.
    }
};
