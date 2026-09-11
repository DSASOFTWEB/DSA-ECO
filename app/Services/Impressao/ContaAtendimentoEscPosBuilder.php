<?php

namespace App\Services\Impressao;

use App\Models\Atendimento;

class ContaAtendimentoEscPosBuilder
{
    public function __construct(protected int $colunas = 48) {}

    public function montar(Atendimento $atendimento): string
    {
        $atendimento->loadMissing(['ponto', 'unidade', 'itens.produto']);
        $linhas = [
            "\x1B\x40",
            $this->centralizar($atendimento->unidade->nome),
            $this->centralizar('CONFERENCIA DE CONTA'),
            str_repeat('-', $this->colunas),
            $atendimento->ponto->identificacao.'  Atendimento #'.$atendimento->id,
            'Abertura: '.$atendimento->aberto_em->format('d/m/Y H:i'),
            'Pessoas: '.$atendimento->quantidade_pessoas,
            str_repeat('-', $this->colunas),
        ];

        foreach ($atendimento->itens->where('status', 'ativo') as $item) {
            $linhas[] = $item->quantidade.'x '.$item->produto->nome;
            if ($item->observacao) {
                $linhas[] = '  Obs: '.$item->observacao;
            }
            $linhas[] = $this->duasColunas('  '.number_format((float) $item->preco_unitario, 2, ',', '.'), 'R$ '.number_format((float) $item->subtotal, 2, ',', '.'));
        }

        $linhas[] = str_repeat('-', $this->colunas);
        $linhas[] = $this->duasColunas('Subtotal', 'R$ '.number_format((float) $atendimento->subtotal, 2, ',', '.'));
        $linhas[] = $this->duasColunas('TOTAL', 'R$ '.number_format((float) $atendimento->valor_total, 2, ',', '.'));
        $linhas[] = $this->centralizar('Documento sem valor fiscal');
        $linhas[] = "\n\n\n\x1D\x56\x00";

        return implode("\n", $linhas);
    }

    protected function centralizar(string $texto): string
    {
        $texto = mb_strimwidth($texto, 0, $this->colunas);

        return str_pad($texto, $this->colunas + intdiv($this->colunas - mb_strlen($texto), 2), ' ', STR_PAD_LEFT);
    }

    protected function duasColunas(string $esquerda, string $direita): string
    {
        $espacos = max(1, $this->colunas - mb_strlen($esquerda) - mb_strlen($direita));

        return mb_strimwidth($esquerda.str_repeat(' ', $espacos).$direita, 0, $this->colunas);
    }
}
