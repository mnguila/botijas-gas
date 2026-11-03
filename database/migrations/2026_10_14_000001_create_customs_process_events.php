<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customs_process_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customs_process_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('type', 30);
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customs_process_id', 'created_at']);
        });

        DB::table('customs_processes')->orderBy('id')->chunkById(100, function ($processes) {
            $users = DB::table('users')->whereIn('id', $processes->pluck('created_by_user_id')->filter())->pluck('name', 'id');
            foreach ($processes as $process) {
                DB::table('customs_process_events')->insert([
                    'customs_process_id' => $process->id,
                    'user_id' => $process->created_by_user_id,
                    'actor_name' => $users[$process->created_by_user_id] ?? null,
                    'type' => 'baseline',
                    'changes' => null,
                    'created_at' => $process->created_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customs_process_events');
    }
};
