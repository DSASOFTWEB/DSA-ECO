<?php

namespace App\Services\Impressao;

use App\Models\Venda;

/**
 * Monta o cupom de venda em ESC/POS (bobina 80mm ≈ 48 colunas).
 * Independente de mike42 — só bytes brutos compatíveis com Epson/Elgin/Bematech.
 */
class EscPosCupomBuilder
{
    public const COLUNAS_80MM = 48;

    public function __construct(
        protected int $colunas = self::COLUNAS_80MM,
    ) {}

    /**
     * @param  array{
     *   empresaNome: string,
     *   empresaCnpj?: ?string,
     *   empresaEndereco?: ?string,
     *   empresaTelefone?: ?string,
     *   valorRecebido?: ?float,
     *   troco?: ?float,
     *   obsLimpa?: ?string,
     * }  $meta
     */
    public function montar(Venda $venda, array $meta = []): string
    {
        $out = '';
        $out .= $this->cmdInit();
        $out .= $this->cmdAlign(1);
        $out .= $this->cmdBold(true);
        $out .= $this->linha($meta['empresaNome'] ?? 'Estabelecimento');
        $out .= $this->cmdBold(false);

        if (! empty($meta['empresaCnpj'])) {
            $out .= $this->linha('CNPJ: '.$meta['empresaCnpj']);
        }
        if (! empty($meta['empresaEndereco'])) {
            foreach ($this->quebrar((string) $meta['empresaEndereco']) as $l) {
                $out .= $this->linha($l);
            }
        }
        if (! empty($meta['empresaTelefone'])) {
            $out .= $this->linha('Tel.: '.$meta['empresaTelefone']);
        }

        $out .= $this->separadorDuplo();
        $out .= $this->cmdBold(true);
        $out .= $this->linha('CUPOM DE VENDA');
        $out .= $this->cmdBold(false);
        $out .= $this->linha('N. '.str_pad((string) $venda->id, 6, '0', STR_PAD_LEFT));
        $out .= $this->linha($venda->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'));
        $out .= $this->cmdAlign(0);

        if ($venda->cliente) {
            $out .= $this->separador('CLIENTE');
            $out .= $this->linhaPar('Nome', $venda->cliente->nome);
            if ($venda->cliente->cpf) {
                $out .= $this->linhaPar('CPF', $venda->cliente->cpf);
            }
        }

        $out .= $this->separador('ITENS');
        $out .= $this->linha($this->padCols('#', 'QTD', 'UNIT', 'TOTAL'));
        $out .= $this->separadorFino();

        foreach ($venda->itens as $idx => $item) {
            $n = (string) ($idx + 1);
            $desc = $item->nomeItem();
            foreach ($this->quebrar($desc, $this->colunas) as $i => $parte) {
                if ($i === 0) {
                    $out .= $this->linha(sprintf('%s %s', $n, $parte));
                } else {
                    $out .= $this->linha('  '.$parte);
                }
            }
            $out .= $this->linha($this->padCols(
                '',
                (string) (int) $item->quantidade,
                number_format((float) $item->preco_unitario, 2, ',', '.'),
                number_format((float) $item->subtotal, 2, ',', '.')
            ));
            if ((float) $item->desconto > 0) {
                $out .= $this->linha('  desc -'.number_format((float) $item->desconto, 2, ',', '.'));
            }
        }

        $out .= $this->separadorFino();
        $qtde = (int) $venda->itens->sum('quantidade');
        $out .= $this->linhaPar('Qtde de itens', (string) $qtde);
        $out .= $this->linhaPar('Subtotal', $this->moeda($venda->valor_bruto));
        if ((float) $venda->desconto > 0) {
            $out .= $this->linhaPar('Desconto', '- '.$this->moeda($venda->desconto));
        }

        $out .= $this->separadorDuplo();
        $out .= $this->cmdBold(true);
        $out .= $this->linhaPar('TOTAL', $this->moeda($venda->valor_total));
        $out .= $this->cmdBold(false);
        $out .= $this->separadorDuplo();

        $forma = match ($venda->forma_pagamento) {
            'dinheiro' => 'Dinheiro',
            'pix' => 'Pix',
            'cartao_credito' => 'Cartao credito',
            'cartao_debito' => 'Cartao debito',
            default => (string) $venda->forma_pagamento,
        };
        $out .= $this->separador('PAGAMENTO');
        $out .= $this->linhaPar($forma, $this->moeda($venda->valor_total));
        if (isset($meta['valorRecebido']) && $meta['valorRecebido'] !== null) {
            $out .= $this->linhaPar('Valor recebido', $this->moeda($meta['valorRecebido']));
        }
        if (! empty($meta['troco']) && (float) $meta['troco'] > 0) {
            $out .= $this->linhaPar('Troco', $this->moeda($meta['troco']));
        }

        if (! empty($meta['obsLimpa'])) {
            $out .= $this->separador('OBSERVACOES');
            foreach ($this->quebrar((string) $meta['obsLimpa']) as $l) {
                $out .= $this->linha($l);
            }
        }

        $out .= $this->separadorFino();
        $out .= $this->linhaPar('Operador', $venda->vendedor?->name ?? '-');
        $out .= $this->linhaPar('Data/Hora', $venda->created_at?->format('d/m/Y H:i') ?? '');
        $out .= $this->linhaPar('Codigo', str_pad((string) $venda->id, 6, '0', STR_PAD_LEFT));
        if ($venda->unidade) {
            $out .= $this->linhaPar('Unidade', $venda->unidade->nome);
        }

        $out .= "\n";
        $out .= $this->cmdAlign(1);
        $out .= $this->linha('OBRIGADO PELA PREFERENCIA');
        $out .= $this->linha('Volte sempre!');
        $out .= $this->linha('*** DOC. AUXILIAR - SEM VALIDADE FISCAL ***');
        $out .= "\n\n";
        $out .= $this->cmdCut();

        return $out;
    }

    protected function cmdInit(): string
    {
        return "\x1B\x40"; // ESC @
    }

    protected function cmdAlign(int $mode): string
    {
        // 0 left, 1 center, 2 right
        return "\x1B\x61".chr($mode);
    }

    protected function cmdBold(bool $on): string
    {
        return "\x1B\x45".($on ? "\x01" : "\x00");
    }

    protected function cmdCut(): string
    {
        // GS V 0 — full cut
        return "\x1D\x56\x00";
    }

    protected function linha(string $texto): string
    {
        return $this->encode($texto)."\n";
    }

    protected function linhaPar(string $esq, string $dir): string
    {
        $esq = $this->truncar($esq, $this->colunas - 1);
        $dir = $this->truncar($dir, $this->colunas - 1);
        $espacos = max(1, $this->colunas - mb_strlen($esq) - mb_strlen($dir));

        return $this->encode($esq.str_repeat(' ', $espacos).$dir)."\n";
    }

    protected function padCols(string $a, string $b, string $c, string $d): string
    {
        // # (3) + qtd (6) + unit (12) + total (resto alinhado à direita)
        $colA = 3;
        $colB = 6;
        $colC = 14;
        $colD = max(8, $this->colunas - $colA - $colB - $colC);

        return str_pad(mb_substr($a, 0, $colA), $colA)
            .str_pad(mb_substr($b, 0, $colB), $colB, ' ', STR_PAD_LEFT)
            .str_pad(mb_substr($c, 0, $colC), $colC, ' ', STR_PAD_LEFT)
            .str_pad(mb_substr($d, 0, $colD), $colD, ' ', STR_PAD_LEFT);
    }

    protected function separador(string $titulo = ''): string
    {
        if ($titulo === '') {
            return $this->linha(str_repeat('-', $this->colunas));
        }
        $titulo = ' '.$titulo.' ';
        $lado = max(0, intdiv($this->colunas - mb_strlen($titulo), 2));

        return $this->linha(str_repeat('-', $lado).$titulo.str_repeat('-', max(0, $this->colunas - $lado - mb_strlen($titulo))));
    }

    protected function separadorDuplo(): string
    {
        return $this->linha(str_repeat('=', $this->colunas));
    }

    protected function separadorFino(): string
    {
        return $this->linha(str_repeat('-', $this->colunas));
    }

    /** @return list<string> */
    protected function quebrar(string $texto, ?int $largura = null): array
    {
        $largura ??= $this->colunas;
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
        if ($texto === '') {
            return [];
        }

        $linhas = [];
        while (mb_strlen($texto) > $largura) {
            $corte = mb_strrpos(mb_substr($texto, 0, $largura + 1), ' ');
            if ($corte === false || $corte < 8) {
                $corte = $largura;
            }
            $linhas[] = trim(mb_substr($texto, 0, $corte));
            $texto = trim(mb_substr($texto, $corte));
        }
        if ($texto !== '') {
            $linhas[] = $texto;
        }

        return $linhas;
    }

    protected function truncar(string $texto, int $max): string
    {
        return mb_strlen($texto) > $max ? mb_substr($texto, 0, $max) : $texto;
    }

    protected function moeda(float|string|null $valor): string
    {
        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }

    /**
     * Impressoras térmicas BR costumam usar CP850/CP860.
     * Fallback para ASCII se iconv falhar.
     */
    protected function encode(string $texto): string
    {
        $texto = str_replace(
            ['—', '–', '“', '”', '’', '…'],
            ['-', '-', '"', '"', "'", '...'],
            $texto
        );

        if (function_exists('iconv')) {
            $cp850 = @iconv('UTF-8', 'CP850//TRANSLIT//IGNORE', $texto);
            if ($cp850 !== false) {
                return $cp850;
            }
        }

        return preg_replace('/[^\x20-\x7E]/', '?', $texto) ?? $texto;
    }
}
