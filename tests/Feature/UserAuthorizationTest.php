<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_do_tenant_pode_abrir_a_edicao_do_proprio_usuario(): void
    {
        $admin = $this->criarAdminTenant();

        $this->actingAs($admin)
            ->get(route('usuarios.edit', $admin))
            ->assertOk();
    }

    public function test_admin_nao_edita_usuario_de_outra_empresa(): void
    {
        $admin = $this->criarAdminTenant();
        $outraEmpresa = Empresa::factory()->create();
        $alvoAlheio = User::factory()->create(['empresa_id' => $outraEmpresa->id]);

        $this->actingAs($admin)
            ->get('/usuarios/'.$alvoAlheio->id.'/edit')
            ->assertNotFound();
    }

    public function test_super_admin_pode_editar_usuario_de_qualquer_empresa(): void
    {
        $admin = $this->criarAdminTenant();
        $super = $this->criarSuperAdmin();

        $this->actingAs($super)
            ->get(route('usuarios.edit', $admin))
            ->assertOk();
    }

    public function test_usuario_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $semPermissao = User::factory()->create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
        ]);
        $alvo = User::factory()->create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
        ]);

        $this->actingAs($semPermissao)
            ->get(route('usuarios.edit', $alvo))
            ->assertForbidden();
    }

    private function criarAdminTenant(): User
    {
        foreach (['usuarios.visualizar', 'usuarios.criar', 'usuarios.editar', 'usuarios.excluir'] as $nome) {
            Permission::findOrCreate($nome, 'web');
        }
        Role::findOrCreate('admin', 'web')->syncPermissions([
            'usuarios.visualizar', 'usuarios.criar', 'usuarios.editar', 'usuarios.excluir',
        ]);

        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $admin = User::factory()->create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
        ]);
        $admin->syncRoles(['admin']);

        return $admin->fresh();
    }

    private function criarSuperAdmin(): User
    {
        Role::findOrCreate('super_admin', 'web');

        $super = User::factory()->create([
            'empresa_id' => null,
            'unidade_id' => null,
            'email' => 'super-test@example.com',
        ]);
        $super->syncRoles(['super_admin']);

        return $super->fresh();
    }
}
