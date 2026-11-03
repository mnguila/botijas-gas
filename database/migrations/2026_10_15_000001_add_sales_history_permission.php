<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => 'sales.history.view'],
            ['label' => 'Consultar histórico geral de vendas', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'sales.history.view')->delete();
    }
};
