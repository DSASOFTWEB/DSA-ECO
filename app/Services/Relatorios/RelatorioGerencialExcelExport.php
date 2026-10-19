<?php

namespace App\Services\Relatorios;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel de qualquer relatório gerencial: cabeçalho, linhas e totais na
 * mesma ordem da tela. Valores numéricos vão como número (somáveis no
 * Excel); datas como texto dd/mm/aaaa.
 */
class RelatorioGerencialExcelExport implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    use Exportable;

    public function __construct(protected array $dados) {}

    public function title(): string
    {
        return mb_substr($this->dados['titulo'], 0, 31);
    }

    public function headings(): array
    {
        return array_map(fn ($c) => $c[0], array_values($this->dados['colunas']));
    }

    public function collection(): Collection
    {
        $linhas = collect($this->dados['linhas'])->map(fn ($l) => $this->linha($l));

        if ($this->dados['totais']) {
            $linhas->push($this->linha($this->dados['totais']));
        }

        return $linhas;
    }

    public function styles(Worksheet $sheet): array
    {
        $estilos = [1 => ['font' => ['bold' => true]]];
        if ($this->dados['totais']) {
            $estilos[count($this->dados['linhas']) + 2] = ['font' => ['bold' => true]];
        }

        return $estilos;
    }

    protected function linha(array $linha): array
    {
        $saida = [];
        foreach ($this->dados['colunas'] as $chave => [, $formato]) {
            $valor = $linha[$chave] ?? null;
            // Linha de totais traz rótulos ("Total") em colunas de data.
            $ehData = $valor instanceof \DateTimeInterface || (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}/', $valor));
            $saida[] = match (true) {
                $valor === null => '',
                in_array($formato, ['moeda', 'numero', 'percentual'], true) && is_numeric($valor) => round((float) $valor, 2),
                $formato === 'data' && $ehData => Carbon::parse($valor)->format('d/m/Y'),
                $formato === 'datahora' && $ehData => Carbon::parse($valor)->format('d/m/Y H:i'),
                default => $this->texto((string) $valor),
            };
        }

        return $saida;
    }

    /** Evita "formula injection" ao abrir no Excel (nomes começando com = + - @). */
    protected function texto(string $valor): string
    {
        return preg_match('/^[=+\-@\t\r]/', $valor) ? "'".$valor : $valor;
    }
}
