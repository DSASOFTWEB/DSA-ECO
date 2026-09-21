<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Plano;
use App\Models\Unidade;
use App\Services\ContratoService;
use App\Services\MensalidadeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cobre o núcleo financeiro: contratar um plano gera a primeira
 * mensalidade e a carteirinha automaticamente, marcar como paga atualiza
 * o status corretamente e cancelar o contrato cancela só as mensalidades
 * futuras — nunca as já vencidas ou já pagas.
 */
class ContratoMensalidadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_contratar_gera_a_primeira_mensalidade_e_a_carteirinha(): void
    {
        Carbon::setTestNow('2026-03-15');

        $cliente = Cliente::factory()->create();
        $plano = Plano::factory()->create(['empresa_id' => $cliente->empresa_id, 'valor' => 150.00]);
        $unidade = Unidade::factory()->create(['empresa_id' => $cliente->empresa_id]);

        $contrato = app(ContratoService::class)->contratar([
            'unidade_id' => $unidade->id,
            'cliente_id' => $cliente->id,
            'plano_id' => $plano->id,
            'data_inicio' => '2026-03-15',
            'dia_vencimento' => 10,
        ]);

        $this->assertSame('ativo', $contrato->status);
        $this->assertSame($cliente->empresa_id, $contrato->empresa_id);
        $this->assertEquals(150.00, (float) $contrato->valor_mensal);
        $this->assertNotNull($contrato->numero_contrato);

        $this->assertCount(1, $contrato->mensalidades);
        $mensalidade = $contrato->mensalidades->first();
        $this->assertSame('pendente', $mensalidade->status);
        $this->assertEquals(150.00, (float) $mensalidade->valor_total);
        // Dia 10 já passou em 15/03 — 1ª mensalidade vence na data de início.
        $this->assertSame('2026-03-15', $mensalidade->data_vencimento->toDateString());

        $this->assertNotNull($cliente->fresh()->carteirinha, 'A carteirinha deveria ser emitida automaticamente ao contratar.');

        Carbon::setTestNow();
    }

    public function test_contratar_com_dia_de_vencimento_futuro_mantem_o_dia(): void
    {
        Carbon::setTestNow('2026-03-05');

        $cliente = Cliente::factory()->create();
        $plano = Plano::factory()->create(['empresa_id' => $cliente->empresa_id, 'valor' => 100.00]);
        $unidade = Unidade::factory()->create(['empresa_id' => $cliente->empresa_id]);

        $contrato = app(ContratoService::class)->contratar([
            'unidade_id' => $unidade->id,
            'cliente_id' => $cliente->id,
            'plano_id' => $plano->id,
            'data_inicio' => '2026-03-05',
            'dia_vencimento' => 10,
        ]);

        $this->assertSame('2026-03-10', $contrato->mensalidades->first()->data_vencimento->toDateString());

        Carbon::setTestNow();
    }

    public function test_atualizar_plano_e_vencimento_recalcula_mensalidade_aberta(): void
    {
        Carbon::setTestNow('2026-03-05');

        $cliente = Cliente::factory()->create();
        $planoA = Plano::factory()->create(['empresa_id' => $cliente->empresa_id, 'valor' => 100.00, 'nome' => 'Basico']);
        $planoB = Plano::factory()->create(['empresa_id' => $cliente->empresa_id, 'valor' => 200.00, 'nome' => 'Premium']);
        $unidade = Unidade::factory()->create(['empresa_id' => $cliente->empresa_id]);

        $contrato = app(ContratoService::class)->contratar([
            'unidade_id' => $unidade->id,
            'cliente_id' => $cliente->id,
            'plano_id' => $planoA->id,
            'data_inicio' => '2026-03-05',
            'dia_vencimento' => 10,
        ]);

        $atualizado = app(ContratoService::class)->atualizar($contrato, [
            'plano_id' => $planoB->id,
            'dia_vencimento' => 20,
            'desconto_percentual' => 0,
        ]);

        $this->assertSame($planoB->id, $atualizado->plano_id);
        $this->assertEquals(200.00, (float) $atualizado->valor_mensal);
        $this->assertSame(20, (int) $atualizado->dia_vencimento);

        $mensalidade = $atualizado->mensalidades->first();
        $this->assertEquals(200.00, (float) $mensalidade->valor_total);
        $this->assertSame('2026-03-20', $mensalidade->data_vencimento->toDateString());
        $this->assertSame('pendente', $mensalidade->status);

        Carbon::setTestNow();
    }

    public function test_contratar_respeita_o_limite_de_dependentes_do_plano(): void
    {
        $cliente = Cliente::factory()->create();
        $plano = Plano::factory()->create(['empresa_id' => $cliente->empresa_id, 'max_dependentes' => 1]);
        $unidade = Unidade::factory()->create(['empresa_id' => $cliente->empresa_id]);

        $dependente1 = $cliente->dependentes()->create(['nome' => 'Dep 1', 'data_nascimento' => '2015-01-01']);
        $dependente2 = $cliente->dependentes()->create(['nome' => 'Dep 2', 'data_nascimento' => '2016-01-01']);

        $this->expectException(\App\Exceptions\NegocioException::class);

        app(ContratoService::class)->contratar([
            'unidade_id' => $unidade->id,
            'cliente_id' => $cliente->id,
            'plano_id' => $plano->id,
            'data_inicio' => now()->toDateString(),
            'dia_vencimento' => 10,
            'dependentes' => [$dependente1->id, $dependente2->id],
        ]);
    }

    public function test_marcar_mensalidade_como_paga_registra_o_pagamento(): void
    {
        $cliente = Cliente::factory()->create();
        $plano = Plano::factory()->create(['empresa_id' => $cliente->empresa_id]);
        $unidade = Unidade::factory()->create(['empresa_id' => $cliente->empresa_id]);

        $contrato = app(ContratoService::class)->contratar([
            'unidade_id' => $unidade->id,
            'cliente_id' => $cliente->id,
            'plano_id' => $plano->id,
            'data_inicio' => now()->toDateString(),
            'dia_vencimento' => 10,
        ]);

        $mensalidade = $contrato->mensalidades->first();

        $mensalidadePaga = app(MensalidadeService::class)->marcarComoPaga(
            $mensalidade,
            gateway: 'mercadopago',
            gatewayPaymentId: 'mp-123456',
            metodoPagamento: 'pix',
        );

        $this->assertSame('pago', $mensalidadePaga->status);
        $this->assertNotNull($mensalidadePaga->data_pagamento);
        $this->assertDatabaseHas('pagamentos', [
            'mensalidade_id' => $mensalidade->id,
            'gateway' => 'mercadopago',
            'gateway_payment_id' => 'mp-123456',
            'status' => 'aprovado',
        ]);

        // Idempotência: chamar de novo não deve duplicar o pagamento.
        app(MensalidadeService::class)->marcarComoPaga($mensalidadePaga, gateway: 'mercadopago');
        $this->assertDatabaseCount('pagamentos', 1);
    }

    public function test_cancelar_contrato_cancela_apenas_mensalidades_futuras_pendentes(): void
    {
        $cliente = Cliente::factory()->create();
        $plano = Plano::factory()->create(['empresa_id' => $cliente->empresa_id]);
        $unidade = Unidade::factory()->create(['empresa_id' => $cliente->empresa_id]);

        $contrato = app(ContratoService::class)->contratar([
            'unidade_id' => $unidade->id,
            'cliente_id' => $cliente->id,
            'plano_id' => $plano->id,
            'data_inicio' => now()->subMonths(2)->toDateString(),
            'dia_vencimento' => 10,
        ]);

        // Mensalidade já vencida (do mês passado) permanece intocada pelo cancelamento.
        $mensalidadeAtrasada = $contrato->mensalidades()->create([
            'empresa_id' => $contrato->empresa_id,
            'competencia' => now()->subMonth()->startOfMonth(),
            'valor_original' => 150,
            'valor_total' => 150,
            'data_vencimento' => now()->subMonth(),
            'status' => 'atrasado',
        ]);

        // Mensalidade futura pendente deve ser cancelada junto com o contrato.
        $mensalidadeFutura = $contrato->mensalidades()->create([
            'empresa_id' => $contrato->empresa_id,
            'competencia' => now()->addMonth()->startOfMonth(),
            'valor_original' => 150,
            'valor_total' => 150,
            'data_vencimento' => now()->addMonth(),
            'status' => 'pendente',
        ]);

        app(ContratoService::class)->cancelar($contrato, 'Solicitação do cliente');

        $this->assertSame('cancelado', $contrato->fresh()->status);
        $this->assertSame('atrasado', $mensalidadeAtrasada->fresh()->status);
        $this->assertSame('cancelado', $mensalidadeFutura->fresh()->status);
    }
}
