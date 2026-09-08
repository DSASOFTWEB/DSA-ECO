<?php

namespace Tests\Feature;

use App\Models\Carteirinha;
use App\Models\Cliente;
use App\Models\Plano;
use App\Models\Unidade;
use App\Services\AcessoService;
use App\Services\ContratoService;
use App\Services\MensalidadeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre a regra de controle de acesso da catraca (App\Services\AcessoService):
 * carteirinha válida com contrato em dia libera a entrada; carteirinha
 * bloqueada, inexistente ou com mensalidade muito atrasada nega — e todo
 * caso, autorizado ou não, fica registrado em `acessos` para auditoria.
 */
class AcessoCarteirinhaTest extends TestCase
{
    use RefreshDatabase;

    protected function criarClienteComContrato(): array
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

        return [$cliente->fresh(), $contrato, $unidade];
    }

    public function test_carteirinha_valida_com_contrato_em_dia_autoriza_o_acesso(): void
    {
        [$cliente, , $unidade] = $this->criarClienteComContrato();

        $resultado = app(AcessoService::class)->validarEEntrar(
            codigo: $cliente->carteirinha->codigo,
            unidadeId: $unidade->id,
        );

        $this->assertTrue($resultado['autorizado']);
        $this->assertDatabaseHas('acessos', [
            'carteirinha_id' => $cliente->carteirinha->id,
            'autorizado' => true,
        ]);
    }

    public function test_codigo_inexistente_nega_o_acesso_e_ainda_assim_registra_a_tentativa(): void
    {
        [, , $unidade] = $this->criarClienteComContrato();

        $resultado = app(AcessoService::class)->validarEEntrar(codigo: 'CART-INEXISTENTE', unidadeId: $unidade->id);

        $this->assertFalse($resultado['autorizado']);
        $this->assertSame('Carteirinha não encontrada', $resultado['motivo']);
        $this->assertDatabaseHas('acessos', ['carteirinha_id' => null, 'autorizado' => false]);
    }

    public function test_carteirinha_bloqueada_nega_o_acesso(): void
    {
        [$cliente, , $unidade] = $this->criarClienteComContrato();

        $carteirinha = $cliente->carteirinha;
        $carteirinha->update(['status' => 'bloqueada', 'motivo_bloqueio' => 'Inadimplência']);

        $resultado = app(AcessoService::class)->validarEEntrar(codigo: $carteirinha->codigo, unidadeId: $unidade->id);

        $this->assertFalse($resultado['autorizado']);
        $this->assertStringContainsString('bloqueada', $resultado['motivo']);
    }

    public function test_mensalidade_muito_atrasada_nega_o_acesso(): void
    {
        [$cliente, $contrato, $unidade] = $this->criarClienteComContrato();

        // Simula uma mensalidade vencida há mais dias que o limite configurado
        // (config/parque.php -> dias_atraso_bloqueia_acesso, padrão 5).
        $contrato->mensalidades()->first()->update([
            'status' => 'atrasado',
            'data_vencimento' => now()->subDays(10)->toDateString(),
        ]);

        $resultado = app(AcessoService::class)->validarEEntrar(
            codigo: $cliente->carteirinha->codigo,
            unidadeId: $unidade->id,
        );

        $this->assertFalse($resultado['autorizado']);
        $this->assertStringContainsString('Inadimplente', $resultado['motivo']);
    }

    public function test_carteirinha_de_dependente_usa_o_contrato_do_titular(): void
    {
        [$cliente, $contrato, $unidade] = $this->criarClienteComContrato();

        $dependente = $cliente->dependentes()->create(['nome' => 'Filho Teste', 'data_nascimento' => '2018-01-01']);
        $contrato->dependentes()->attach($dependente->id);

        $carteirinha = app(\App\Services\CarteirinhaService::class)->emitirParaDependente($dependente);

        $resultado = app(AcessoService::class)->validarEEntrar(codigo: $carteirinha->codigo, unidadeId: $unidade->id);

        $this->assertTrue($resultado['autorizado']);
    }
}
