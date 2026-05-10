<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique('name', 'uq_roles__name');
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique('name', 'uq_permissions__name');
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
            $table->string('user_type');

            $table->primary(['user_id', 'role_id', 'user_type'], 'pk_role_user');

            $table->foreign('role_id', 'fk_role_user__role_id__roles')
                ->references('id')->on('roles')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->index(['user_id', 'user_type'], 'idx_role_user__user_id_user_type');
        });

        Schema::create('permission_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('user_id');
            $table->string('user_type');

            $table->primary(['user_id', 'permission_id', 'user_type'], 'pk_permission_user');

            $table->foreign('permission_id', 'fk_permission_user__permission_id__permissions')
                ->references('id')->on('permissions')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->index(['user_id', 'user_type'], 'idx_permission_user__user_id_user_type');
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->primary(['permission_id', 'role_id'], 'pk_permission_role');

            $table->foreign('permission_id', 'fk_permission_role__permission_id__permissions')
                ->references('id')->on('permissions')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('role_id', 'fk_permission_role__role_id__roles')
                ->references('id')->on('roles')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
