<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TerminalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_com_permissao_pode_abrir_criacao_de_terminal_sem_ser_admin(): void
    {
        foreach (['terminais.visualizar', 'terminais.criar'] as $nome) {
            Permission::findOrCreate($nome, 'web');
        }

        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
        ]);
        $user->givePermissionTo(['terminais.visualizar', 'terminais.criar']);

        $this->actingAs($user->fresh())
            ->get(route('terminais.create'))
            ->assertOk();
    }

    public function test_usuario_sem_permissao_recebe_403_ao_criar_terminal(): void
    {
        Permission::findOrCreate('terminais.visualizar', 'web');

        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
        ]);
        $user->givePermissionTo('terminais.visualizar');

        $this->actingAs($user->fresh())
            ->get(route('terminais.create'))
            ->assertForbidden();
    }
}
