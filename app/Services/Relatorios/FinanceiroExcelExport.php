<?php

namespace App\Services\Relatorios;

use App\Models\Mensalidade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportação detalhada (linha a linha) das mensalidades do mês, para quem
 * precisa conferir/conciliar no Excel — complementa o PDF, que é só o
 * resumo gerencial.
 */
class FinanceiroExcelExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected Carbon $mes;

    public function comMes(Carbon $mes): static
    {
        $this->mes = $mes;

        return $this;
    }

    public function collection(): Collection
    {
        return Mensalidade::with('contrato.cliente', 'contrato.unidade')
            ->whereBetween('data_vencimento', [$this->mes->copy()->startOfMonth(), $this->mes->copy()->endOfMonth()])
            ->orderBy('data_vencimento')
            ->get();
    }

    public function headings(): array
    {
        return ['Cliente', 'Unidade', 'Competência', 'Vencimento', 'Valor original', 'Desconto', 'Valor total', 'Status', 'Data pagamento'];
    }

    /**
     * Neutraliza "CSV/Formula Injection": se o nome do cliente/unidade
     * começar com =, +, -, @ ou tab/CR, o Excel pode interpretar como
     * fórmula ao abrir o arquivo — prefixa com apóstrofo pra forçar texto.
     */
    protected function sanitizarCelula(?string $valor): string
    {
        $valor = (string) $valor;

        return preg_match('/^[=+\-@\t\r]/', $valor) ? "'".$valor : $valor;
    }

    public function map($mensalidade): array
    {
        return [
            $this->sanitizarCelula($mensalidade->contrato->cliente->nome),
            $this->sanitizarCelula($mensalidade->contrato->unidade->nome),
            $mensalidade->competencia->format('m/Y'),
            $mensalidade->data_vencimento->format('d/m/Y'),
            (float) $mensalidade->valor_original,
            (float) $mensalidade->desconto,
            (float) $mensalidade->valor_total,
            ucfirst($mensalidade->status),
            $mensalidade->data_pagamento?->format('d/m/Y') ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function download(string $nomeArquivo): \Symfony\Component\HttpFoundation\Response
    {
        return Excel::download($this, $nomeArquivo);
    }
}
