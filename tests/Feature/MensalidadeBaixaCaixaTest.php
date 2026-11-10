<?php

namespace Tests\Feature;

use App\Models\Caixa;
use App\Models\CaixaMovimentacao;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\Mensalidade;
use App\Models\Plano;
use App\Models\Terminal;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Baixa manual da mensalidade: o operador escolhe o caixa/terminal (antes
 * entrava no primeiro caixa aberto encontrado).
 */
class MensalidadeBaixaCaixaTest extends TestCase
{
    use RefreshDatabase;

    public function test_baixa_entra_no_caixa_escolhido(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $outraUnidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'name' => 'Maria']);
        Gate::before(fn () => true);
        $this->actingAs($user);

        $portaria = $this->caixa($empresa, $unidade, $user, 'PORTARIA');
        $restaurante = $this->caixa($empresa, $unidade, $user, 'RESTAURANTE');
        $deOutraUnidade = $this->caixa($empresa, $outraUnidade, $user, 'POUSADA');
        $fechado = $this->caixa($empresa, $unidade, $user, 'ANTIGO', 'fechado');

        $mensalidade = $this->mensalidade($empresa, $unidade);

        // Dois abertos na unidade: nada pré-marcado, operador escolhe.
        $this->get(route('mensalidades.show', $mensalidade))->assertOk()
            ->assertSee('Caixa / terminal')
            ->assertSee('Escolha o caixa...')
            ->assertSee('PORTARIA')->assertSee('RESTAURANTE')
            ->assertDontSee('POUSADA')->assertDontSee('ANTIGO');

        // Sem escolher: recusa. Caixa fechado ou de outra unidade: recusa.
        $this->post(route('mensalidades.baixar', $mensalidade), ['metodo_pagamento' => 'dinheiro'])->assertSessionHasErrors('caixa_id');
        foreach ([$fechado, $deOutraUnidade] as $invalido) {
            $this->post(route('mensalidades.baixar', $mensalidade), ['metodo_pagamento' => 'dinheiro', 'caixa_id' => $invalido->id])
                ->assertSessionHas('erro', fn ($e) => str_contains($e, 'não está aberto'));
        }
        $this->assertSame('pendente', $mensalidade->fresh()->status);

        // Escolheu o RESTAURANTE (o segundo): entra nele, não no primeiro.
        $this->post(route('mensalidades.baixar', $mensalidade), ['metodo_pagamento' => 'cartao_debito', 'caixa_id' => $restaurante->id])
            ->assertSessionHas('sucesso', 'Pagamento registrado no caixa RESTAURANTE.');

        $this->assertSame('pago', $mensalidade->fresh()->status);
        $this->assertSame(1, CaixaMovimentacao::where('caixa_id', $restaurante->id)->count());
        $this->assertSame(0, CaixaMovimentacao::where('caixa_id', $portaria->id)->count());

        // Próxima mensalidade já vem com o RESTAURANTE marcado (lembra a escolha).
        $outra = $this->mensalidade($empresa, $unidade);
        $this->get(route('mensalidades.show', $outra))->assertSee('<option value="'.$restaurante->id.'" selected', false);
    }

    protected function caixa(Empresa $empresa, Unidade $unidade, User $user, string $terminal, string $status = 'aberto'): Caixa
    {
        $t = Terminal::create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'nome' => $terminal, 'ativo' => true]);

        return Caixa::create([
            'empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'terminal_id' => $t->id, 'usuario_abertura_id' => $user->id,
            'data_abertura' => now(), 'valor_abertura' => 0, 'status' => $status,
        ]);
    }

    protected function mensalidade(Empresa $empresa, Unidade $unidade): Mensalidade
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);
        $contrato = Contrato::create([
            'empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'cliente_id' => $cliente->id,
            'plano_id' => Plano::factory()->create(['empresa_id' => $empresa->id])->id,
            'data_inicio' => now(), 'dia_vencimento' => 10, 'valor_mensal' => 100, 'status' => 'ativo',
        ]);

        return Mensalidade::create([
            'contrato_id' => $contrato->id, 'empresa_id' => $empresa->id, 'competencia' => now()->startOfMonth()->addMonths(rand(1, 500)),
            'valor_original' => 100, 'valor_total' => 100, 'data_vencimento' => now()->addDays(5)->toDateString(), 'status' => 'pendente',
        ]);
    }
}
