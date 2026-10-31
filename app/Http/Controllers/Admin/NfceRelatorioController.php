<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\IntegrationException;
use App\Http\Controllers\Controller;
use App\Mail\FechamentoFiscalMail;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\FechamentoFiscal;
use App\Services\Fiscal\FechamentoFiscalService;
use App\Services\Fiscal\FiscalXmlStorageService;
use App\Services\Integrations\EvolutionApiService;
use App\Support\Telefone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Relatório de NFC-e emitidas (DANFE, impressão, XML) e fechamento do mês
 * (pacote ZIP para o contador, enviado por e-mail ou WhatsApp).
 */
class NfceRelatorioController extends Controller
{
    public function __construct(protected FechamentoFiscalService $service) {}

    public function index(Request $request): View
    {
        Gate::authorize('auditoria.visualizar');

        $filtros = $request->validate([
            'inicio' => ['nullable', 'date'],
            'fim' => ['nullable', 'date'],
            'status' => ['nullable', 'in:autorizado,rejeitado,erro,cancelado,processando'],
            'serie' => ['nullable', 'integer'],
        ]);
        $inicio = isset($filtros['inicio']) ? Carbon::parse($filtros['inicio']) : now()->startOfMonth();
        $fim = isset($filtros['fim']) ? Carbon::parse($filtros['fim']) : now();

        $consulta = $this->service->notas($inicio, $fim, $filtros);

        return view('relatorios.nfce', [
            'notas' => (clone $consulta)->with(['cliente', 'hospedagem.quarto'])->paginate(50)->withQueryString(),
            'resumo' => $this->service->resumo((clone $consulta)->get(['id', 'status', 'serie', 'numero', 'valor_total', 'forma_pagamento', 'autorizado_em', 'created_at'])),
            'inicio' => $inicio,
            'fim' => $fim,
            'filtros' => $filtros,
            'series' => DocumentoFiscal::where('modelo', DocumentoFiscal::MODELO_NFCE)->whereNotNull('serie')->distinct()->orderBy('serie')->pluck('serie'),
            'fechamentos' => FechamentoFiscal::where('modelo', DocumentoFiscal::MODELO_NFCE)->latest('id')->limit(12)->get(),
            'whatsappIntegrado' => app(EvolutionApiService::class)->configurada(),
            'empresa' => Empresa::find($request->user()->empresa_id),
        ]);
    }

    public function xml(DocumentoFiscal $documento, FiscalXmlStorageService $xmlStorage): Response|RedirectResponse
    {
        Gate::authorize('auditoria.visualizar');

        $conteudo = $xmlStorage->ler($documento->xml_path) ?: $documento->xml_protocolado ?: $documento->xml;
        if (! filled($conteudo)) {
            return back()->with('erro', 'XML deste documento não está disponível.');
        }

        $nome = sprintf('NFCe-S%s-N%06d%s.xml', $documento->serie ?? 0, $documento->numero ?? 0, $documento->chave ? '-'.$documento->chave : '');

        return response($conteudo, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nome.'"',
        ]);
    }

    public function fechar(Request $request): RedirectResponse
    {
        Gate::authorize('auditoria.visualizar');
        $dados = $request->validate(['mes' => ['required', 'date_format:Y-m']]);

        // "!" zera os campos não informados: sem ele o Carbon completa com o dia
        // de hoje e, num dia 31, "2026-09" vira 1º de outubro.
        $fechamento = $this->service->solicitar(Carbon::createFromFormat('!Y-m', $dados['mes'])->startOfMonth(), $request->user());

        return back()->with(...match ($fechamento->status) {
            FechamentoFiscal::STATUS_PRONTO => ['sucesso', "Mês {$fechamento->rotuloCompetencia()} fechado: {$fechamento->quantidade} NFC-e no pacote {$fechamento->arquivo_nome}."],
            FechamentoFiscal::STATUS_ERRO => ['erro', "Não foi possível gerar o pacote: {$fechamento->erro}"],
            default => ['info', "O pacote de {$fechamento->rotuloCompetencia()} está sendo gerado. Atualize a página em alguns minutos."],
        });
    }

    public function download(FechamentoFiscal $fechamento): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('auditoria.visualizar');

        return $this->arquivo($fechamento);
    }

    /** Link temporário (assinado, sem login) — usado no e-mail e no WhatsApp. */
    public function downloadAssinado(Request $request, int $fechamento): BinaryFileResponse|RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        return $this->arquivo(FechamentoFiscal::withoutGlobalScopes()->findOrFail($fechamento));
    }

    public function enviarEmail(Request $request, FechamentoFiscal $fechamento): RedirectResponse
    {
        Gate::authorize('auditoria.visualizar');
        $dados = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'mensagem' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $fechamento->estaPronto()) {
            return back()->with('erro', 'O pacote deste mês ainda não está pronto.');
        }

        try {
            Mail::to($dados['email'])->send(new FechamentoFiscalMail(
                $fechamento, Empresa::findOrFail($fechamento->empresa_id), $this->linkTemporario($fechamento), $dados['mensagem'] ?? null,
            ));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('erro', 'Não foi possível enviar o e-mail: '.$e->getMessage());
        }

        $aviso = config('mail.default') === 'log' ? ' (atenção: o e-mail está em modo "log" — configure o SMTP no .env para enviar de verdade)' : '';

        return back()->with('sucesso', "Pacote {$fechamento->rotuloCompetencia()} enviado para {$dados['email']}.{$aviso}");
    }

    /**
     * Com a Evolution API configurada, envia o ZIP como documento. Sem ela,
     * abre o WhatsApp Web/app com a mensagem e o link temporário.
     */
    public function enviarWhatsapp(Request $request, FechamentoFiscal $fechamento, EvolutionApiService $whatsapp): RedirectResponse
    {
        Gate::authorize('auditoria.visualizar');
        $dados = $request->validate(['telefone' => ['required', 'string', 'max:20']]);

        if (! $fechamento->estaPronto()) {
            return back()->with('erro', 'O pacote deste mês ainda não está pronto.');
        }

        $numero = Telefone::paraWhatsapp($dados['telefone']);
        if (! $numero) {
            return back()->with('erro', 'Informe o WhatsApp com DDD, ex.: (83) 99999-9999.');
        }

        $empresa = Empresa::findOrFail($fechamento->empresa_id);
        $texto = "Fechamento de NFC-e {$fechamento->rotuloCompetencia()} — {$empresa->nome}\n"
            ."{$fechamento->quantidade} nota(s), total R$ ".number_format((float) $fechamento->valor_total, 2, ',', '.');

        if ($whatsapp->configurada()) {
            try {
                $whatsapp->enviarDocumento(
                    $numero,
                    base64_encode(Storage::disk('local')->get($fechamento->arquivo_path)),
                    $fechamento->arquivo_nome,
                    'application/zip',
                    $texto,
                );

                return back()->with('sucesso', "Pacote enviado pelo WhatsApp para +{$numero}.");
            } catch (IntegrationException $e) {
                report($e);
                // Cai no link abaixo para não deixar o usuário sem envio.
            }
        }

        return redirect()->away('https://wa.me/'.$numero.'?text='.rawurlencode($texto."\n\nBaixe o pacote (XML, PDF e resumo): ".$this->linkTemporario($fechamento)));
    }

    protected function arquivo(FechamentoFiscal $fechamento): BinaryFileResponse|RedirectResponse
    {
        if (! $fechamento->estaPronto()) {
            return back()->with('erro', 'O pacote deste mês não está disponível. Gere o fechamento novamente.');
        }

        return response()->download(Storage::disk('local')->path($fechamento->arquivo_path), $fechamento->arquivo_nome, ['Content-Type' => 'application/zip']);
    }

    protected function linkTemporario(FechamentoFiscal $fechamento): string
    {
        return URL::temporarySignedRoute('fiscal.fechamentos.publico', now()->addDays(7), ['fechamento' => $fechamento->id]);
    }
}
