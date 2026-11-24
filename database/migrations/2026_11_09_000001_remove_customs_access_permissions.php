<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('permissions')->whereIn('key', [
            'customs.manage',
            'access.grant.aduaneiros',
        ])->delete();
    }

    public function down(): void
    {
        // Atribuições antigas não podem ser reconstruídas com segurança.
    }
};
