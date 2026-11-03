<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => 'sales.profit.view'],
            ['label' => 'Ver margem de lucro das vendas']
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'sales.profit.view')->delete();
    }
};
