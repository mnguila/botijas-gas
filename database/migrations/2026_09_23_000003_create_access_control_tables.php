<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('id');
            $table->string('email')->nullable()->change();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_super_admin')->default(false);
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->timestamps();
        });
        Schema::create('access_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('access_group_user', function (Blueprint $table) {
            $table->foreignId('access_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['access_group_id', 'user_id']);
        });
        Schema::create('access_group_permission', function (Blueprint $table) {
            $table->foreignId('access_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['access_group_id', 'permission_id']);
        });
        Schema::create('permission_user', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('access_group_permission');
        Schema::dropIfExists('access_group_user');
        Schema::dropIfExists('access_groups');
        Schema::dropIfExists('permissions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'is_active', 'is_super_admin']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
