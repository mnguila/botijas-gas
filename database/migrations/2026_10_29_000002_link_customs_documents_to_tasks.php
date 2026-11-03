<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customs_process_documents', function (Blueprint $table) {
            $table->foreignId('customs_process_task_id')->nullable()->after('customs_process_id')
                ->constrained('customs_process_tasks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customs_process_documents', fn (Blueprint $table) => $table->dropConstrainedForeignId('customs_process_task_id'));
    }
};
