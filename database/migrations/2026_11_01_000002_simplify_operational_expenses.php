<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('operational_expenses', function (Blueprint $table) {
            $table->foreignId('cash_account_id')->nullable()->change();
        });

        // A permissão antiga deixa de existir; os dados históricos não são apagados.
        DB::table('permissions')->where('key', 'cash.manage')->delete();
    }

    public function down(): void
    {
        DB::table('permissions')->updateOrInsert(['key' => 'cash.manage'], ['label' => 'Gerir caixas e entradas de fundos']);
    }
};
