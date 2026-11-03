<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('permissions')->whereNotIn('key', array_keys(config('access.permissions')))->delete();
        foreach (config('access.permissions') as $key => $label) {
            Permission::updateOrCreate(['key' => $key], ['label' => $label]);
        }
    }
}
