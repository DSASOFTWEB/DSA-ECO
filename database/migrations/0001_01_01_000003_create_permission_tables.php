<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration padrão do pacote spatie/laravel-permission (v6), reproduzida
 * manualmente aqui para já ficar versionada junto ao restante do schema.
 * Ao rodar `composer install`, publique/compare com:
 *   php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="permission-migrations"
 * Caso a versão instalada gere uma migration diferente, mantenha apenas uma das duas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $teamsEnabled = config('permission.teams', false);

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table) use ($teamsEnabled) {
            $table->id();
            if ($teamsEnabled) {
                $table->unsignedBigInteger('team_id')->nullable();
                $table->index('team_id', 'roles_team_id_index');
            }
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            if ($teamsEnabled) {
                $table->unique(['team_id', 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        Schema::create('model_has_permissions', function (Blueprint $table) use ($teamsEnabled) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->cascadeOnDelete();

            if ($teamsEnabled) {
                $table->unsignedBigInteger('team_id');
                $table->index('team_id', 'model_has_permissions_team_id_index');
                $table->primary(['team_id', 'permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
            } else {
                $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
            }
        });

        Schema::create('model_has_roles', function (Blueprint $table) use ($teamsEnabled) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();

            if ($teamsEnabled) {
                $table->unsignedBigInteger('team_id');
                $table->index('team_id', 'model_has_roles_team_id_index');
                $table->primary(['team_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
            } else {
                $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
            }
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
