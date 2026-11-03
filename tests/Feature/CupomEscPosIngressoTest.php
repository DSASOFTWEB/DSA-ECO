<?php

namespace Tests\Feature;

use App\Models\Acesso;
use App\Models\Caixa;
use App\Models\Empresa;
use App\Models\TipoEntrada;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Cupom ESC/POS igual ao da tela: com os QR dos ingressos para a portaria
 * validar a entrada e o código de barras da venda.
 */
class CupomEscPosIngressoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cupom_escpos_traz_o_qr_de_cada_ingresso(): void
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
        $tipo = TipoEntrada::create(['empresa_id' => $empresa->id, 'nome' => 'Entrada Avulsa Infantil', 'valor' => 15, 'eh_plano' => false, 'ativo' => true]);

        $this->post(route('vendas.store'), [
            'caixa_id' => $caixa->id, 'forma_pagamento' => 'dinheiro',
            'itens' => [['tipo_entrada_id' => $tipo->id, 'quantidade' => 2]],
        ])->assertSessionHasNoErrors();

        $venda = Venda::latest('id')->firstOrFail();
        $codigos = Acesso::where('venda_id', $venda->id)->whereNotNull('codigo_validacao')->pluck('codigo_validacao');
        $this->assertCount(2, $codigos);

        // A tela já mostrava os QR; o ESC/POS precisa trazer os mesmos códigos.
        $tela = $this->get(route('vendas.comprovante', $venda))->assertOk();
        $bytes = $this->get(route('vendas.comprovante.escpos', $venda))->assertOk()->getContent();

        $this->assertStringContainsString('INGRESSOS / QR', $bytes);
        foreach ($codigos as $codigo) {
            $tela->assertSee($codigo);
            // QR nativo (GS ( k "grava") com o código exato que a portaria valida.
            $tamanho = strlen($codigo) + 3;
            $this->assertStringContainsString("\x1D(k".chr($tamanho % 256).chr(intdiv($tamanho, 256))."\x31\x50\x30".$codigo, $bytes);
            $this->assertStringContainsString($codigo."\n", $bytes, 'código legível embaixo do QR');
        }
        $this->assertSame(2, substr_count($bytes, "\x1D(k\x03\x00\x31\x51\x30"), 'imprime um QR por ingresso');

        // Código de barras CODE128 da venda (mesmo número da tela) e total destacado.
        $this->assertStringContainsString("\x1D\x6B\x49\x0F{B".str_pad((string) $venda->id, 13, '0', STR_PAD_LEFT), $bytes);
        $this->assertStringContainsString("\x1D\x21\x01", $bytes);

        // Venda só de produto de consumo: modelo simples (sem barras, QR nem total dobrado).
        $produto = \App\Models\Produto::create([
            'empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'nome' => 'Refrigerante', 'tipo_item' => 'produto',
            'preco_custo' => 2, 'preco_venda' => 8, 'controla_estoque' => false, 'ativo' => true,
        ]);
        $this->post(route('vendas.store'), [
            'caixa_id' => $caixa->id, 'forma_pagamento' => 'dinheiro',
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
        ])->assertSessionHasNoErrors();

        $consumo = $this->get(route('vendas.comprovante.escpos', Venda::latest('id')->firstOrFail()))->getContent();
        $this->assertStringContainsString('Refrigerante', $consumo);
        $this->assertStringNotContainsString('INGRESSOS / QR', $consumo);
        $this->assertStringNotContainsString("\x1D(k", $consumo);
        $this->assertStringNotContainsString("\x1D\x6B", $consumo);
        $this->assertStringNotContainsString("\x1D\x21\x01", $consumo);
        $this->assertStringEndsWith(\App\Services\Impressao\EscPosCupomBuilder::CORTE, $consumo, 'mas avança e corta igual');
    }
}
