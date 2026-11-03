<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach (config('access.permissions') as $key => $label) {
            if ($key === 'sales.points.assign' || str_starts_with($key, 'access.grant.')) {
                DB::table('permissions')->updateOrInsert(['key' => $key], ['label' => $label]);
            }
        }
    }

    public function down(): void
    {
        // As atribuições existentes permanecem intactas numa reversão.
    }
};
