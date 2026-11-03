<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customs_processes', function (Blueprint $table) {
            $table->string('entry_number', 100)->nullable()->change();
            $table->date('entry_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('customs_processes')->whereNull('entry_number')->orderBy('id')->chunkById(100, function ($processes) {
            foreach ($processes as $process) {
                DB::table('customs_processes')->where('id', $process->id)
                    ->update(['entry_number' => 'PROCESSO-'.$process->id]);
            }
        });
        DB::table('customs_processes')->whereNull('entry_date')->update(['entry_date' => DB::raw('date(created_at)')]);
        Schema::table('customs_processes', function (Blueprint $table) {
            $table->string('entry_number', 100)->nullable(false)->change();
            $table->date('entry_date')->nullable(false)->change();
        });
    }
};
