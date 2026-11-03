<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customs_processes', function (Blueprint $table) {
            $table->foreignId('responsible_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
        });
        DB::table('customs_processes')->whereNull('responsible_user_id')->update([
            'responsible_user_id' => DB::raw('created_by_user_id'),
        ]);

        Schema::create('customs_process_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customs_process_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('pendente');
            $table->date('due_date')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customs_process_id', 'status']);
        });

        Schema::create('customs_process_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customs_process_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customs_process_documents');
        Schema::dropIfExists('customs_process_tasks');
        Schema::table('customs_processes', fn (Blueprint $table) => $table->dropConstrainedForeignId('responsible_user_id'));
    }
};
