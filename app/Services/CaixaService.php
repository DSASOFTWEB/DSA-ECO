<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Caixa;
use App\Models\CaixaMovimentacao;
use App\Models\Terminal;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CaixaService
{
    public function abrir(Terminal $terminal, User $usuario, float $valorAbertura): Caixa
    {
        return DB::transaction(function () use ($terminal, $usuario, $valorAbertura) {
            if (Caixa::where('terminal_id', $terminal->id)->where('status', 'aberto')->lockForUpdate()->exists()) {
                throw new NegocioException('Já existe um caixa aberto para este terminal.');
            }

            return Caixa::create([
                'empresa_id' => $terminal->empresa_id,
                'unidade_id' => $terminal->unidade_id,
                'terminal_id' => $terminal->id,
                'usuario_abertura_id' => $usuario->id,
                'data_abertura' => now(),
                'valor_abertura' => $valorAbertura,
                'status' => 'aberto',
            ]);
        });
    }

    public function registrarMovimentacao(Caixa $caixa, array $dados): CaixaMovimentacao
    {
        if (! $caixa->estaAberto()) {
            throw new NegocioException('Não é possível movimentar um caixa fechado.');
        }

        return DB::transaction(fn () => $caixa->movimentacoes()->create($dados));
    }

    /**
     * Fecha o caixa calculando o saldo esperado pelo sistema (abertura +
     * entradas - saídas) e registrando a diferença em relação ao valor
     * contado fisicamente pelo operador (sobra ou falta de caixa).
     */
    public function fechar(Caixa $caixa, User $usuario, float $valorInformado): Caixa
    {
        if (! $caixa->estaAberto()) {
            throw new NegocioException('Este caixa já está fechado.');
        }

        return DB::transaction(function () use ($caixa, $usuario, $valorInformado) {
            $entradas = (float) $caixa->movimentacoes()->where('tipo', 'entrada')->sum('valor');
            $saidas = (float) $caixa->movimentacoes()->where('tipo', 'saida')->sum('valor');
            $valorSistema = round((float) $caixa->valor_abertura + $entradas - $saidas, 2);

            $caixa->update([
                'usuario_fechamento_id' => $usuario->id,
                'data_fechamento' => now(),
                'valor_fechamento_informado' => $valorInformado,
                'valor_fechamento_sistema' => $valorSistema,
                'diferenca' => round($valorInformado - $valorSistema, 2),
                'status' => 'fechado',
            ]);

            return $caixa->refresh();
        });
    }

    /**
     * Aceita $unidade nula de propósito: um admin/gerente sem unidade fixa
     * (unidade_id nulo em `users`) não deve derrubar a tela com um
     * TypeError. Uma unidade pode ter vários terminais com caixa aberto
     * simultaneamente — isto devolve só o primeiro encontrado, o que basta
     * pros fluxos de baixo risco (baixa manual de mensalidade/conta) que
     * não precisam saber exatamente em qual terminal lançar.
     */
    public function caixaAbertoDaUnidade(?Unidade $unidade): ?Caixa
    {
        if (! $unidade) {
            return null;
        }

        return Caixa::where('unidade_id', $unidade->id)->where('status', 'aberto')->first();
    }

    public function caixaAbertoDoTerminal(?Terminal $terminal): ?Caixa
    {
        if (! $terminal) {
            return null;
        }

        return Caixa::where('terminal_id', $terminal->id)->where('status', 'aberto')->first();
    }

    /**
     * Todos os caixas abertos de uma unidade (um por terminal), com o
     * terminal carregado — usado pro operador da própria unidade escolher
     * em qual terminal está vendendo, quando há mais de um aberto.
     */
    public function caixasAbertosDaUnidade(int $unidadeId): Collection
    {
        return Caixa::with('terminal', 'unidade')->where('unidade_id', $unidadeId)->where('status', 'aberto')->get();
    }

    /**
     * Todos os caixas abertos da empresa (entre unidades e terminais), com
     * terminal e unidade carregados — usado quando o operador (admin/
     * gerente) não tem unidade fixa ("todas as unidades") e por isso não dá
     * pra descobrir sozinho qual caixa usar: se houver só um aberto, a tela
     * usa direto; se houver mais de um, o operador escolhe.
     */
    public function caixasAbertosDaEmpresa(int $empresaId): Collection
    {
        return Caixa::with('terminal', 'unidade')->where('empresa_id', $empresaId)->where('status', 'aberto')->get();
    }
}
