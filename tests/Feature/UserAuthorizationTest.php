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

    public function test_admin_pode_salvar_modulos_de_acesso_no_usuario(): void
    {
        $admin = $this->criarAdminTenant();
        $this->seedPermissoesCompletas();

        $alvo = User::factory()->create([
            'empresa_id' => $admin->empresa_id,
            'unidade_id' => $admin->unidade_id,
        ]);

        $this->actingAs($admin)
            ->put(route('usuarios.update', $alvo), [
                'name' => $alvo->name,
                'email' => $alvo->email,
                'status' => 'ativo',
                'sincronizar_acesso' => '1',
                'roles' => ['vendedor'],
                'permissions' => ['clientes.visualizar', 'vendas.criar', 'vendas.visualizar'],
            ])
            ->assertRedirect(route('usuarios.index'));

        $alvo->refresh();
        // Pacote do vendedor não está completo → perfil cai; ficam só as permissões marcadas.
        $this->assertFalse($alvo->hasRole('vendedor'));
        $this->assertTrue($alvo->hasPermissionTo('clientes.visualizar'));
        $this->assertTrue($alvo->hasPermissionTo('vendas.criar'));
        $this->assertFalse($alvo->can('contratos.criar'));
        $this->assertEqualsCanonicalizing(
            ['clientes.visualizar', 'vendas.criar', 'vendas.visualizar'],
            $alvo->getDirectPermissions()->pluck('name')->all()
        );
    }

    public function test_desmarcar_pousada_remove_acesso_mesmo_com_perfil_admin(): void
    {
        $admin = $this->criarAdminTenant();
        $this->seedPermissoesCompletas();

        $alvo = User::factory()->create([
            'empresa_id' => $admin->empresa_id,
            'unidade_id' => $admin->unidade_id,
        ]);
        $alvo->syncRoles(['admin']);
        $alvo->syncPermissions(\App\Support\ModulosPermissoes::todasPermissoes());

        $semPousada = array_values(array_filter(
            \App\Support\ModulosPermissoes::todasPermissoes(),
            fn (string $p): bool => ! str_starts_with($p, 'pousada.')
        ));

        $this->actingAs($admin)
            ->put(route('usuarios.update', $alvo), [
                'name' => $alvo->name,
                'email' => $alvo->email,
                'status' => 'ativo',
                'sincronizar_acesso' => '1',
                'roles' => ['admin'],
                'permissions' => $semPousada,
            ])
            ->assertRedirect(route('usuarios.index'));

        $alvo->refresh();
        $this->assertFalse($alvo->hasRole('admin'));
        $this->assertFalse($alvo->can('pousada.visualizar'));
        $this->assertFalse($alvo->can('pousada.reservar'));
        $this->assertTrue($alvo->can('clientes.visualizar'));
        $this->assertTrue($alvo->can('acessos.validar'));
    }

    public function test_criar_usuario_nao_quebra_quando_permissao_do_catalogo_falta_no_banco(): void
    {
        $admin = $this->criarAdminTenant();
        $this->seedPermissoesCompletas();
        Permission::where('name', 'pousada.comodato')->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->actingAs($admin)
            ->post(route('usuarios.store'), [
                'name' => 'Novo Recepcionista',
                'email' => 'novo@example.com',
                'password' => 'Senha@12345',
                'sincronizar_acesso' => '1',
                'roles' => ['recepcao'],
                'permissions' => \App\Support\ModulosPermissoes::permissoesPorPapel()['recepcao'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('usuarios.index'));

        $novo = User::where('email', 'novo@example.com')->firstOrFail();
        $this->assertTrue($novo->hasPermissionTo('pousada.comodato'));
    }

    public function test_quem_nao_e_admin_recebe_aviso_ao_marcar_perfil_administrador(): void
    {
        $admin = $this->criarAdminTenant();
        $this->seedPermissoesCompletas();
        $gerente = User::factory()->create([
            'empresa_id' => $admin->empresa_id,
            'unidade_id' => $admin->unidade_id,
        ]);
        $gerente->syncRoles(['gerente']);

        $this->actingAs($gerente)
            ->from(route('usuarios.create'))
            ->post(route('usuarios.store'), [
                'name' => 'Tentativa Admin',
                'email' => 'tentativa@example.com',
                'password' => 'Senha@12345',
                'sincronizar_acesso' => '1',
                'roles' => ['admin'],
                'permissions' => \App\Support\ModulosPermissoes::todasPermissoes(),
            ])
            ->assertRedirect(route('usuarios.create'))
            ->assertSessionHasErrors('roles');

        $this->assertDatabaseMissing('users', ['email' => 'tentativa@example.com']);

        $this->get(route('usuarios.create'))
            ->assertOk()
            ->assertSee("togglePapel('gerente'", false)
            ->assertDontSee("togglePapel('admin'", false);
    }

    private function seedPermissoesCompletas(): void
    {
        foreach (\App\Support\ModulosPermissoes::todasPermissoes() as $nome) {
            Permission::findOrCreate($nome, 'web');
        }
        foreach (\App\Support\ModulosPermissoes::permissoesPorPapel() as $papel => $lista) {
            Role::findOrCreate($papel, 'web')->syncPermissions($lista);
        }
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
