<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->foreignId('location_id')->nullable()->unique()->constrained('locations')->restrictOnDelete();
            $table->bigInteger('balance_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('operational_expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date');
            $table->string('category', 100);
            $table->text('description');
            $table->unsignedBigInteger('amount_cents');
            $table->foreignId('cash_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('vehicle_plate', 30)->nullable();
            $table->string('payee', 120)->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_name')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['status', 'expense_date']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('operational_expense_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->bigInteger('amount_cents');
            $table->string('note', 500);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['cash_account_id', 'created_at']);
        });

        DB::table('cash_accounts')->insert([
            'name' => 'Caixa Central', 'balance_cents' => 0, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (config('access.permissions') as $key => $label) {
            if (str_starts_with($key, 'expenses.') || $key === 'cash.manage' || $key === 'access.grant.financeiro') {
                DB::table('permissions')->updateOrInsert(['key' => $key], ['label' => $label]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('operational_expenses');
        Schema::dropIfExists('cash_accounts');
        // As permissões atribuídas a utilizadores e grupos permanecem intactas.
    }
};
