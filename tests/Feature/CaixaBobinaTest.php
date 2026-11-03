<?php

namespace Tests\Feature;

use App\Models\Caixa;
use App\Models\CaixaMovimentacao;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CaixaService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Fechamento de caixa na bobina 80mm (navegador e ESC/POS).
 */
class CaixaBobinaTest extends TestCase
{
    use RefreshDatabase;

    protected Caixa $caixa;

    protected User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $empresa = Empresa::factory()->create(['nome' => 'Eco Clube Catolé']);
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id, 'nome' => 'UNIDADE CATOLE']);
        $this->operador = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'name' => 'Maria Caixa']);
        Gate::before(fn () => true);
        $this->actingAs($this->operador);

        $this->caixa = Caixa::create([
            'empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'usuario_abertura_id' => $this->operador->id,
            'data_abertura' => now()->subHours(8), 'valor_abertura' => 100, 'status' => 'aberto',
        ]);

        // 80 dinheiro + 60 débito + 15 pix em vendas; 214 hospedagem dinheiro; sangria 50 em dinheiro.
        $this->mov('entrada', 'venda', 'dinheiro', 80, 'Venda #40 (Dinheiro)');
        $this->mov('entrada', 'venda', 'cartao_debito', 60, 'Venda #40 (Cartão de débito)');
        $this->mov('entrada', 'venda', 'pix', 15, 'Venda #41');
        $this->mov('entrada', 'hospedagem', 'dinheiro', 214, 'Check-out hospedagem #7');
        $this->mov('saida', 'sangria', 'dinheiro', 50, 'Sangria para o cofre');
    }

    public function test_parcial_do_caixa_aberto(): void
    {
        $this->get(route('caixas.bobina', $this->caixa))->assertOk()
            ->assertSee('PARCIAL DE CAIXA')
            ->assertSeeInOrder(['Valor de abertura', 'R$ 100,00', '(+) Entradas', 'R$ 369,00', 'Saídas', 'R$ 50,00', 'SALDO ATÉ AGORA', 'R$ 419,00'])
            ->assertDontSee('DIFERENÇA');
    }

    public function test_fechamento_com_formas_gaveta_e_diferenca(): void
    {
        // Sistema: 100 + 369 − 50 = 419. Contou 410 → falta 9.
        app(CaixaService::class)->fechar($this->caixa, $this->operador, 410);

        $this->get(route('caixas.show', $this->caixa))->assertSee(route('caixas.bobina', $this->caixa), false);

        $this->get(route('caixas.bobina', $this->caixa))->assertOk()
            ->assertSee('FECHAMENTO DE CAIXA')
            ->assertSee('Caixa Nº '.str_pad((string) $this->caixa->id, 6, '0', STR_PAD_LEFT))
            ->assertSeeInOrder(['SALDO DO SISTEMA', 'R$ 419,00', 'Valor contado', 'R$ 410,00', 'DIFERENÇA', '− R$ 9,00', 'FALTA NO CAIXA'])
            // Por forma: dinheiro 294 (80+214) com saída de 50; débito 60; pix 15.
            ->assertSeeInOrder(['POR FORMA DE PAGAMENTO', 'Dinheiro', 'R$ 294,00', 'saídas', 'R$ 50,00', 'Cartão de débito', 'R$ 60,00', 'Pix', 'R$ 15,00'])
            // Gaveta: 100 + 294 − 50 = 344.
            ->assertSeeInOrder(['DINHEIRO NA GAVETA', 'R$ 344,00'])
            ->assertSeeInOrder(['POR CATEGORIA', 'Hospedagem (1)', 'R$ 214,00', 'Venda (3)', 'R$ 155,00', '(−) Sangria (1)', 'R$ 50,00'])
            ->assertSee('Sangria para o cofre')
            ->assertSee('Conferente');

        $this->get(route('caixas.bobina', [$this->caixa, 'resumido' => 1]))->assertDontSee('Sangria para o cofre')->assertSee('DINHEIRO NA GAVETA');
    }

    public function test_escpos_do_fechamento(): void
    {
        app(CaixaService::class)->fechar($this->caixa, $this->operador, 419);

        $resposta = $this->get(route('caixas.bobina.escpos', $this->caixa))->assertOk();
        $this->assertSame('application/octet-stream', $resposta->headers->get('Content-Type'));

        $texto = iconv('CP850', 'UTF-8', $resposta->getContent());
        $this->assertStringContainsString('FECHAMENTO DE CAIXA', $texto);
        $this->assertMatchesRegularExpression('/SALDO DO SISTEMA\s+R\$ 419,00/', $texto);
        $this->assertMatchesRegularExpression('/DINHEIRO NA GAVETA\s+R\$ 344,00/', $texto);
        $this->assertStringContainsString('Caixa conferido, sem diferenca', $texto);
        $this->assertMatchesRegularExpression('/Cartão de débito\s+R\$ 60,00/u', $texto);
        $this->assertStringEndsWith(\App\Services\Impressao\EscPosCupomBuilder::CORTE, $resposta->getContent(), 'avança até a lâmina e corta');

        // Linhas cabem em 48 colunas.
        foreach (explode("\n", $texto) as $linha) {
            // Tira os comandos (ESC @, ESC a n, ESC E n, GS V 0) antes de medir.
            $visivel = preg_replace('/\x1B@|\x1B[aE][\x00-\x02]|\x1Bd\x04|\x1DVA\x10/', '', $linha);
            $this->assertLessThanOrEqual(48, mb_strlen($visivel), $visivel);
        }
    }

    public function test_nao_abre_caixa_de_outra_empresa(): void
    {
        $this->actingAs(User::factory()->create(['empresa_id' => Empresa::factory()->create()->id]));

        $this->get(route('caixas.bobina', $this->caixa))->assertNotFound();
    }

    protected function mov(string $tipo, string $categoria, string $forma, float $valor, string $descricao): void
    {
        CaixaMovimentacao::create([
            'caixa_id' => $this->caixa->id, 'tipo' => $tipo, 'categoria' => $categoria, 'descricao' => $descricao,
            'valor' => $valor, 'forma_pagamento' => $forma, 'usuario_id' => $this->operador->id,
        ]);
    }
}
