<?php

namespace Database\Seeders;

use App\Support\ModulosPermissoes;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissoes = ModulosPermissoes::todasPermissoes();

        foreach ($permissoes as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']);
        }

        // super_admin: enxerga tudo via Gate::before (AuthServiceProvider).
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        foreach (ModulosPermissoes::permissoesPorPapel() as $papel => $lista) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web'])
                ->syncPermissions($lista);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
