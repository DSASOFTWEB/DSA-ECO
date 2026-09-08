<?php

namespace App\Services\Relatorios;

use App\Models\Carteirinha;
use App\Models\Dependente;
use App\Services\CarteirinhaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Impressão de carteirinhas em PDF — uma (individual) ou várias por página
 * (lote), no tamanho padrão de cartão (CR80, 85.6mm x 54mm) para imprimir
 * em papel cartão e recortar.
 */
class CarteirinhaPdfExport
{
    public function __construct(protected CarteirinhaService $carteirinhaService) {}

    /**
     * @param  Collection<int,Carteirinha>  $carteirinhas
     */
    public function gerar(Collection $carteirinhas): PdfDocument
    {
        // Aceita tanto a Collection "de banco" (várias, já vem assim) quanto
        // uma Collection comum (impressão individual, criada com collect()) —
        // loadMissing só existe na Eloquent Collection.
        $carteirinhas = EloquentCollection::make($carteirinhas->all());
        $carteirinhas->loadMissing(['cliente.empresa', 'cliente.contratoAtivo.plano', 'dependente.cliente.empresa', 'dependente.contratos.plano']);

        $cartoes = $carteirinhas->map(function (Carteirinha $carteirinha) {
            $titular = $carteirinha->titular();
            $ehDependente = $titular instanceof Dependente;
            $clienteBase = $ehDependente ? $titular?->cliente : $titular;

            $contratoAtivo = $ehDependente
                ? $titular?->contratos->firstWhere('status', 'ativo')
                : $titular?->contratoAtivo;

            return [
                'carteirinha' => $carteirinha,
                'nome' => $titular?->nome ?? 'Titular não encontrado',
                'ehDependente' => $ehDependente,
                'planoNome' => $contratoAtivo?->plano?->nome,
                'validade' => $contratoAtivo?->data_fim?->format('m/Y'),
                'empresa' => $clienteBase?->empresa,
                'fotoDataUri' => $this->fotoDataUri($titular?->foto_path),
                'qrDataUri' => $this->carteirinhaService->gerarQrCodeDataUri($carteirinha, 160),
            ];
        });

        return Pdf::loadView('carteirinhas.pdf.lote', [
            'cartoes' => $cartoes,
            'geradoEm' => now(),
        ])->setPaper('a4', 'portrait');
    }

    protected function fotoDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return "data:{$mime};base64,".base64_encode(Storage::disk('public')->get($path));
    }
}
