<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Plano;
use App\Models\Unidade;
use App\Models\User;
use App\Services\ContratoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClienteDetalheTest extends TestCase
{
    use RefreshDatabase;

    public function test_detalhe_mostra_carteirinha_contrato_e_historico_de_pagamentos(): void
    {
        Carbon::setTestNow('2026-03-15');

        $cliente = Cliente::factory()->create();
        $plano = Plano::factory()->create(['empresa_id' => $cliente->empresa_id, 'nome' => 'Plano Família', 'valor' => 150]);
        $unidade = Unidade::factory()->create(['empresa_id' => $cliente->empresa_id]);
        $user = User::factory()->create([
            'empresa_id' => $cliente->empresa_id,
            'unidade_id' => $unidade->id,
        ]);

        foreach (['clientes.visualizar', 'contratos.visualizar', 'mensalidades.visualizar', 'carteirinhas.visualizar'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $user->givePermissionTo(['clientes.visualizar', 'contratos.visualizar', 'mensalidades.visualizar', 'carteirinhas.visualizar']);
        Gate::before(fn () => true);

        $contrato = app(ContratoService::class)->contratar([
            'unidade_id' => $unidade->id,
            'cliente_id' => $cliente->id,
            'plano_id' => $plano->id,
            'data_inicio' => '2026-03-15',
            'dia_vencimento' => 10,
        ]);

        $cliente->refresh();
        $mensalidade = $contrato->mensalidades->first();

        $html = $this->actingAs($user)
            ->get(route('clientes.show', $cliente))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Imprimir carteirinha', $html);
        $this->assertStringContainsString(route('carteirinhas.imprimir', $cliente->carteirinha), $html);
        $this->assertStringContainsString('Ver contrato', $html);
        $this->assertStringContainsString(route('contratos.show', $contrato), $html);
        $this->assertStringContainsString(route('contratos.pdf', $contrato), $html);
        $this->assertStringContainsString('Histórico de pagamentos', $html);
        $this->assertStringContainsString(route('mensalidades.show', $mensalidade), $html);
        $this->assertStringContainsString('Plano Família', $html);
        $this->assertStringContainsString('name="parentesco"', $html);
        $this->assertStringContainsString('Filho(a)', $html);
        $this->assertStringContainsString('Sobrinho(a)', $html);
        $this->assertStringContainsString('Tio(a)', $html);
        $this->assertStringContainsString('Outro', $html);

        Carbon::setTestNow();
    }
}
