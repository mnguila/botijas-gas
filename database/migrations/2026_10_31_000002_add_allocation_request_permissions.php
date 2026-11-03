<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach (['allocations.request', 'allocations.approve', 'allocations.dispatch', 'allocations.receive'] as $key) {
            DB::table('permissions')->updateOrInsert(['key' => $key], ['label' => config('access.permissions')[$key]]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('key', ['allocations.request', 'allocations.approve', 'allocations.dispatch', 'allocations.receive'])->delete();
    }
};
