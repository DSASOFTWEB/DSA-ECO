<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Caixa;
use App\Models\CaixaMovimentacao;
use App\Models\Terminal;
use App\Models\TransferenciaCaixa;
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
            $totais = $this->calcularTotais($caixa);

            $caixa->update([
                'usuario_fechamento_id' => $usuario->id,
                'data_fechamento' => now(),
                'valor_fechamento_informado' => $valorInformado,
                'valor_fechamento_sistema' => $totais['saldo_atual'],
                'diferenca' => round($valorInformado - $totais['saldo_atual'], 2),
                'status' => 'fechado',
            ]);

            return $caixa->refresh();
        });
    }

    /**
     * Soma entradas/saídas de um caixa (aberto ou fechado) e devolve o
     * saldo atual (abertura + entradas - saídas) — usado tanto por
     * fechar() (compara com o valor contado pelo operador) quanto por
     * resumoCaixaAberto() (mostra "quanto tem agora" sem precisar fechar).
     *
     * @return array{valor_abertura: float, entradas: float, saidas: float, saldo_atual: float}
     */
    protected function calcularTotais(Caixa $caixa): array
    {
        $entradas = (float) $caixa->movimentacoes()->where('tipo', 'entrada')->sum('valor');
        $saidas = (float) $caixa->movimentacoes()->where('tipo', 'saida')->sum('valor');
        $valorAbertura = (float) $caixa->valor_abertura;

        return [
            'valor_abertura' => $valorAbertura,
            'entradas' => $entradas,
            'saidas' => $saidas,
            'saldo_atual' => round($valorAbertura + $entradas - $saidas, 2),
        ];
    }

    /**
     * "Quanto tem neste caixa agora" — mesmo cálculo do fechamento, mas sem
     * fechar o caixa, mais a quebra de entradas por forma de pagamento
     * (dinheiro/pix/cartão/...), usado no card de saldo por caixa/terminal.
     *
     * @return array{caixa: Caixa, valor_abertura: float, entradas: float, saidas: float, saldo_atual: float, por_forma_pagamento: array<string, float>}
     */
    public function resumoCaixaAberto(Caixa $caixa): array
    {
        $totais = $this->calcularTotais($caixa);

        $porFormaPagamento = $caixa->movimentacoes()
            ->where('tipo', 'entrada')
            ->selectRaw("COALESCE(forma_pagamento, 'outro') as forma, SUM(valor) as total")
            ->groupBy('forma')
            ->pluck('total', 'forma')
            ->map(fn ($valor) => (float) $valor)
            ->all();

        return ['caixa' => $caixa, ...$totais, 'por_forma_pagamento' => $porFormaPagamento];
    }

    /**
     * Move valor de um caixa aberto pra outro: lança saída na origem e
     * entrada no destino, e guarda o vínculo entre as duas movimentações em
     * TransferenciaCaixa (auditoria/rastreio). Nunca altera o saldo geral
     * consolidado da empresa — só desloca entre dois caixas.
     */
    public function transferir(Caixa $origem, Caixa $destino, float $valor, User $usuario, ?string $observacao = null): TransferenciaCaixa
    {
        if ($origem->id === $destino->id) {
            throw new NegocioException('O caixa de origem e destino não podem ser o mesmo.');
        }

        if ($valor <= 0) {
            throw new NegocioException('O valor da transferência precisa ser maior que zero.');
        }

        return DB::transaction(function () use ($origem, $destino, $valor, $usuario, $observacao) {
            $origemTravada = Caixa::whereKey($origem->id)->lockForUpdate()->firstOrFail();
            $destinoTravado = Caixa::whereKey($destino->id)->lockForUpdate()->firstOrFail();

            if (! $origemTravada->estaAberto() || ! $destinoTravado->estaAberto()) {
                throw new NegocioException('Os dois caixas precisam estar abertos para transferir.');
            }

            $saldoOrigem = $this->calcularTotais($origemTravada)['saldo_atual'];

            if ($saldoOrigem < $valor) {
                throw new NegocioException('Saldo insuficiente no caixa de origem para esta transferência.');
            }

            $movimentacaoSaida = $origemTravada->movimentacoes()->create([
                'tipo' => 'saida',
                'categoria' => 'transferencia_saida',
                'descricao' => "Transferência para {$destinoTravado->terminal?->nome}",
                'valor' => $valor,
                'usuario_id' => $usuario->id,
            ]);

            $movimentacaoEntrada = $destinoTravado->movimentacoes()->create([
                'tipo' => 'entrada',
                'categoria' => 'transferencia_entrada',
                'descricao' => "Transferência de {$origemTravada->terminal?->nome}",
                'valor' => $valor,
                'usuario_id' => $usuario->id,
            ]);

            $transferencia = TransferenciaCaixa::create([
                'empresa_id' => $origemTravada->empresa_id,
                'caixa_origem_id' => $origemTravada->id,
                'caixa_destino_id' => $destinoTravado->id,
                'valor' => $valor,
                'usuario_id' => $usuario->id,
                'observacao' => $observacao,
                'movimentacao_saida_id' => $movimentacaoSaida->id,
                'movimentacao_entrada_id' => $movimentacaoEntrada->id,
            ]);

            // Aponta as duas movimentações pra transferência que as gerou —
            // além de rastreio, isso as marca como "não manuais"
            // (CaixaMovimentacao::eManual()), impedindo editar/estornar só
            // um lado da transferência pela tela de movimentações.
            $movimentacaoSaida->update(['referencia_type' => TransferenciaCaixa::class, 'referencia_id' => $transferencia->id]);
            $movimentacaoEntrada->update(['referencia_type' => TransferenciaCaixa::class, 'referencia_id' => $transferencia->id]);

            return $transferencia;
        });
    }

    /**
     * Estorna um lançamento manual: cria um lançamento reverso (tipo
     * oposto, mesmo valor, referência apontando pro original) e marca o
     * original como estornado — nunca apaga/sobrescreve o histórico.
     * Bloqueado pra lançamentos com origem automática (venda, mensalidade,
     * hospedagem, conta, transferência) e pra caixa já fechado — ver
     * CaixaMovimentacao::podeEstornar().
     */
    public function estornar(CaixaMovimentacao $movimentacao, User $usuario): CaixaMovimentacao
    {
        if (! $movimentacao->podeEstornar()) {
            throw new NegocioException('Este lançamento não pode ser estornado.');
        }

        return DB::transaction(function () use ($movimentacao, $usuario) {
            $movimentacao->caixa->movimentacoes()->create([
                'tipo' => $movimentacao->tipo === 'entrada' ? 'saida' : 'entrada',
                'categoria' => 'ajuste',
                'descricao' => "Estorno de #{$movimentacao->id}: {$movimentacao->descricao}",
                'valor' => $movimentacao->valor,
                'forma_pagamento' => $movimentacao->forma_pagamento,
                'referencia_type' => CaixaMovimentacao::class,
                'referencia_id' => $movimentacao->id,
                'usuario_id' => $usuario->id,
            ]);

            $movimentacao->update([
                'estornado_em' => now(),
                'estornado_por_id' => $usuario->id,
            ]);

            return $movimentacao->fresh();
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
