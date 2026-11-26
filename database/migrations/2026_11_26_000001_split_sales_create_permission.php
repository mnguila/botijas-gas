<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $legacy = DB::table('permissions')->where('key', 'sales.create')->first();
            $newIds = [];

            foreach (['sales.wholesale.create', 'sales.unit.create'] as $key) {
                DB::table('permissions')->updateOrInsert(
                    ['key' => $key],
                    ['label' => config('access.permissions')[$key], 'updated_at' => now()]
                );
                $newIds[] = DB::table('permissions')->where('key', $key)->value('id');
            }

            if (! $legacy) return;

            foreach (['permission_user' => 'user_id', 'access_group_permission' => 'access_group_id'] as $table => $ownerColumn) {
                $owners = DB::table($table)->where('permission_id', $legacy->id)->pluck($ownerColumn);
                foreach ($owners as $ownerId) {
                    foreach ($newIds as $permissionId) {
                        DB::table($table)->insertOrIgnore([
                            $ownerColumn => $ownerId,
                            'permission_id' => $permissionId,
                        ]);
                    }
                }
            }

            DB::table('permissions')->where('id', $legacy->id)->delete();
        });
    }

    public function down(): void
    {
        // A separação pode ter sido personalizada; não restaurar uma permissão ampla.
    }
};
