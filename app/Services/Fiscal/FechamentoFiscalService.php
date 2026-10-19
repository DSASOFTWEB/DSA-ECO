<?php

namespace App\Services\Fiscal;

use App\Jobs\GerarFechamentoFiscalJob;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\FechamentoFiscal;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * NFC-e emitidas e fechamento do mês: pacote ZIP para o contador com os
 * XMLs, os DANFEs em PDF e o resumo (PDF + CSV), na pasta
 * "<Empresa> - NFC-e MM-AAAA".
 */
class FechamentoFiscalService
{
    /** Acima disso o pacote é gerado na fila (PDF de cada nota leva tempo). */
    public const LIMITE_GERACAO_IMEDIATA = 80;

    public function __construct(
        protected DanfceService $danfce,
        protected FiscalXmlStorageService $xmlStorage,
    ) {}

    /** NFC-e do período (data da autorização ou, sem ela, da criação). */
    public function notas(Carbon $inicio, Carbon $fim, array $filtros = []): Builder
    {
        $data = 'coalesce(documentos_fiscais.autorizado_em, documentos_fiscais.created_at)';

        return DocumentoFiscal::query()
            ->where('modelo', DocumentoFiscal::MODELO_NFCE)
            ->whereRaw("{$data} between ? and ?", [$inicio->copy()->startOfDay(), $fim->copy()->endOfDay()])
            ->when($filtros['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filtros['serie'] ?? null, fn ($q, $s) => $q->where('serie', $s))
            ->orderByRaw("{$data} desc")
            ->orderByDesc('id');
    }

    /** Totais para os cards e o resumo (só notas autorizadas somam valor). */
    public function resumo(Collection $notas): array
    {
        $autorizadas = $notas->where('status', DocumentoFiscal::STATUS_AUTORIZADO);
        $data = fn (DocumentoFiscal $n) => ($n->autorizado_em ?? $n->created_at)->format('Y-m-d');

        return [
            'quantidade' => $autorizadas->count(),
            'valor_total' => round((float) $autorizadas->sum('valor_total'), 2),
            'ticket_medio' => $autorizadas->count() ? round((float) $autorizadas->sum('valor_total') / $autorizadas->count(), 2) : 0,
            'canceladas' => $notas->where('status', DocumentoFiscal::STATUS_CANCELADO)->count(),
            'nao_autorizadas' => $notas->whereIn('status', [DocumentoFiscal::STATUS_REJEITADO, DocumentoFiscal::STATUS_ERRO])->count(),
            'por_serie' => $autorizadas->groupBy('serie')->map(fn ($g, $serie) => [
                'serie' => $serie, 'quantidade' => $g->count(), 'valor' => round((float) $g->sum('valor_total'), 2),
                'primeiro' => $g->min('numero'), 'ultimo' => $g->max('numero'),
            ])->sortKeys()->values()->all(),
            'por_dia' => $autorizadas->groupBy($data)->map(fn ($g, $dia) => [
                'dia' => $dia, 'quantidade' => $g->count(), 'valor' => round((float) $g->sum('valor_total'), 2),
            ])->sortKeys()->values()->all(),
            'por_forma' => $autorizadas->groupBy(fn ($n) => $n->forma_pagamento ?: 'outro')->map(fn ($g, $forma) => [
                'forma' => \App\Support\Financeiro::FORMAS_PAGAMENTO[$forma]['label'] ?? ucfirst((string) $forma),
                'quantidade' => $g->count(), 'valor' => round((float) $g->sum('valor_total'), 2),
            ])->sortByDesc('valor')->values()->all(),
        ];
    }

    /**
     * "Fechar o mês": registra o fechamento e gera o pacote — na hora para
     * meses pequenos, na fila para meses grandes.
     */
    public function solicitar(Carbon $mes, User $usuario): FechamentoFiscal
    {
        $competencia = $mes->copy()->startOfMonth();

        $fechamento = FechamentoFiscal::create([
            'empresa_id' => $usuario->empresa_id,
            'modelo' => DocumentoFiscal::MODELO_NFCE,
            'competencia' => $competencia->toDateString(),
            'status' => FechamentoFiscal::STATUS_PROCESSANDO,
            'gerado_por_id' => $usuario->id,
        ]);

        $quantidade = $this->notas($competencia, $competencia->copy()->endOfMonth())->where('status', DocumentoFiscal::STATUS_AUTORIZADO)->count();

        if ($quantidade <= self::LIMITE_GERACAO_IMEDIATA) {
            $this->gerar($fechamento);
        } else {
            GerarFechamentoFiscalJob::dispatch($fechamento->id);
        }

        return $fechamento->fresh();
    }

    public function gerar(FechamentoFiscal $fechamento): FechamentoFiscal
    {
        try {
            return $this->montarPacote($fechamento);
        } catch (\Throwable $e) {
            report($e);
            $fechamento->update(['status' => FechamentoFiscal::STATUS_ERRO, 'erro' => $e->getMessage()]);

            return $fechamento->fresh();
        }
    }

    public function nomePasta(Empresa $empresa, Carbon $competencia): string
    {
        $nome = Str::of($empresa->nome)->ascii()->replaceMatches('/[^A-Za-z0-9 ._-]/', '')->squish()->limit(60, '');

        return trim($nome.' - NFC-e '.$competencia->format('m-Y'));
    }

    protected function montarPacote(FechamentoFiscal $fechamento): FechamentoFiscal
    {
        $empresa = Empresa::findOrFail($fechamento->empresa_id);
        $inicio = $fechamento->competencia->copy()->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $notas = $this->notas($inicio, $fim)
            ->withoutGlobalScopes()
            ->where('documentos_fiscais.empresa_id', $empresa->id)
            ->reorder('serie')->orderBy('numero')
            ->get();
        $resumo = $this->resumo($notas);
        $pasta = $this->nomePasta($empresa, $inicio);

        $relativo = "fechamentos/{$empresa->id}/{$pasta}.zip";
        Storage::disk('local')->makeDirectory("fechamentos/{$empresa->id}");
        $caminho = Storage::disk('local')->path($relativo);
        @unlink($caminho);

        $zip = new ZipArchive;
        if ($zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o arquivo ZIP do fechamento.');
        }

        $falhas = [];
        foreach ($notas->whereIn('status', [DocumentoFiscal::STATUS_AUTORIZADO, DocumentoFiscal::STATUS_CANCELADO]) as $nota) {
            $base = sprintf('NFCe-S%s-N%06d', $nota->serie ?? 0, $nota->numero ?? 0);
            $subpasta = $nota->status === DocumentoFiscal::STATUS_CANCELADO ? 'Canceladas/' : '';

            $xml = $this->xmlStorage->ler($nota->xml_path) ?: $nota->xml_protocolado ?: $nota->xml;
            if (filled($xml)) {
                $zip->addFromString("{$pasta}/XML/{$subpasta}{$base}".($nota->chave ? "-{$nota->chave}" : '').'.xml', $xml);
            } else {
                $falhas[] = "{$base}: XML não encontrado";
            }

            if ($nota->status === DocumentoFiscal::STATUS_AUTORIZADO) {
                try {
                    $zip->addFromString("{$pasta}/PDF/{$base}.pdf", $this->danfce->pdf($nota));
                } catch (\Throwable $e) {
                    $falhas[] = "{$base}: PDF não gerado ({$e->getMessage()})";
                }
            }
        }

        $zip->addFromString("{$pasta}/Resumo.pdf", Pdf::loadView('fiscal.fechamento-resumo', [
            'empresa' => $empresa, 'competencia' => $inicio, 'resumo' => $resumo, 'notas' => $notas, 'falhas' => $falhas, 'geradoEm' => now(),
        ])->setOption('isFontSubsettingEnabled', true)->setPaper('a4')->output());
        $zip->addFromString("{$pasta}/Resumo.csv", $this->csv($notas));
        $zip->close();

        $fechamento->update([
            'status' => FechamentoFiscal::STATUS_PRONTO,
            'quantidade' => $resumo['quantidade'],
            'valor_total' => $resumo['valor_total'],
            'arquivo_path' => $relativo,
            'arquivo_nome' => "{$pasta}.zip",
            'erro' => $falhas ? implode(' | ', $falhas) : null,
            'gerado_em' => now(),
        ]);

        return $fechamento->fresh();
    }

    /** Lista das notas para abrir no Excel (separador ; e BOM UTF-8). */
    protected function csv(Collection $notas): string
    {
        $linhas = [['Série', 'Número', 'Data', 'Chave de acesso', 'Situação', 'Forma de pagamento', 'Valor']];
        foreach ($notas as $n) {
            $linhas[] = [
                $n->serie, $n->numero, ($n->autorizado_em ?? $n->created_at)->format('d/m/Y H:i'),
                $n->chave ? "'".$n->chave : '', ucfirst((string) $n->status),
                \App\Support\Financeiro::FORMAS_PAGAMENTO[$n->forma_pagamento]['label'] ?? (string) $n->forma_pagamento,
                number_format((float) $n->valor_total, 2, ',', ''),
            ];
        }

        return "\xEF\xBB\xBF".implode("\r\n", array_map(fn ($l) => implode(';', array_map(fn ($c) => str_replace(';', ',', (string) $c), $l)), $linhas))."\r\n";
    }
}
