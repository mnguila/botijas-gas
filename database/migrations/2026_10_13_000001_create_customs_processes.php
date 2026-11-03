<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customs_processes', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number', 100)->unique();
            $table->date('entry_date');
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('invoice_reference')->nullable();
            $table->string('invoice_attachment_path')->nullable();
            $table->string('invoice_attachment_name')->nullable();
            $table->text('subject')->nullable();
            $table->string('at_technician', 150)->nullable();
            $table->string('stage', 40);
            $table->text('communication')->nullable();
            $table->string('communication_attachment_path')->nullable();
            $table->string('communication_attachment_name')->nullable();
            $table->date('exit_date')->nullable();
            $table->string('archive_location', 255)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['stage', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customs_processes');
    }
};
