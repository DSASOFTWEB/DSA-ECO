<?php

namespace Tests\Feature;

use App\Models\Caixa;
use App\Models\Empresa;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Ao finalizar no PDV, o cupom volta sozinho para uma venda nova depois de
 * imprimir (sem passar pela lista de vendas).
 */
class PdvProximaVendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cupom_recem_finalizado_volta_para_nova_venda_e_aberto_depois_nao(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $operador = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);
        Gate::before(fn () => true);
        $this->actingAs($operador);

        $caixa = Caixa::create([
            'empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'usuario_abertura_id' => $operador->id,
            'data_abertura' => now(), 'valor_abertura' => 0, 'status' => 'aberto',
        ]);
        $produto = Produto::create([
            'empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'nome' => 'Água', 'tipo_item' => 'produto',
            'preco_custo' => 1, 'preco_venda' => 4, 'controla_estoque' => false, 'ativo' => true,
        ]);

        $this->post(route('vendas.store'), [
            'caixa_id' => $caixa->id, 'forma_pagamento' => 'dinheiro',
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
        ])->assertSessionHas('pdv_continuar', true);

        $venda = Venda::latest('id')->firstOrFail();
        $this->get(route('vendas.comprovante', $venda))->assertOk()
            ->assertSee('const CONTINUAR_PDV = true', false)
            ->assertSee('Nova venda')
            ->assertSee(route('vendas.create'), false);

        // Reaberto depois (pela lista): não sai pulando para o PDV.
        $this->get(route('vendas.comprovante', $venda))->assertSee('const CONTINUAR_PDV = false', false);
    }
}
