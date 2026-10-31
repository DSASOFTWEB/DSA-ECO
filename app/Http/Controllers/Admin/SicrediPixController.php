<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\IntegrationException;
use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Services\Integrations\Pix\SicrediCertificadoService;
use App\Services\Integrations\Pix\SicrediPixGateway;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Apoio à adesão da API Pix Sicredi na aba Financeiro de Minha Empresa:
 * gera o CSR (com a chave guardada aqui), testa a conexão e entrega o
 * passo a passo em PDF para o cliente levar à cooperativa.
 */
class SicrediPixController extends Controller
{
    public function __construct(protected SicrediCertificadoService $certificados) {}

    public function gerarCsr(Request $request): RedirectResponse
    {
        Gate::authorize('empresa.gerenciar');
        $dados = $request->validate(['nome_parceiro' => ['nullable', 'string', 'max:40']]);
        $empresa = $this->empresa($request);

        try {
            $this->certificados->gerarCsr($empresa, (string) ($dados['nome_parceiro'] ?? ''));
        } catch (NegocioException $e) {
            return $this->voltar()->with('erro', $e->getMessage());
        }

        return $this->voltar()->with('sucesso', 'CSR gerado. Baixe o arquivo .csr e registre no Portal do Desenvolvedor Sicredi (Certificados e Credenciais).');
    }

    public function baixarCsr(Request $request): Response
    {
        Gate::authorize('empresa.gerenciar');
        $empresa = $this->empresa($request);
        $csr = $this->certificados->csrPendente($empresa);
        abort_unless($csr, 404, 'Nenhum CSR pendente. Gere um novo CSR.');

        $cnpj = preg_replace('/\D+/', '', (string) $empresa->cnpj) ?: 'empresa';

        return response($csr, 200, [
            'Content-Type' => 'application/pkcs10',
            'Content-Disposition' => "attachment; filename=\"sicredi-pix-{$cnpj}.csr\"",
        ]);
    }

    public function testar(Request $request): RedirectResponse
    {
        Gate::authorize('empresa.gerenciar');
        $empresa = $this->empresa($request);

        try {
            $resultado = (new SicrediPixGateway($empresa))->testarConexao();
        } catch (IntegrationException $e) {
            return $this->voltar()->with('erro', $e->getMessage());
        }

        $ambiente = $resultado['ambiente'] === 'homologacao' ? 'homologação' : 'produção';

        return $this->voltar()->with('sucesso', "Conexão com o Sicredi ({$ambiente}) OK — certificado e credenciais aceitos. Escopos: {$resultado['escopos']}.");
    }

    /** Passo a passo da adesão em PDF, já com os dados desta empresa. */
    public function passoAPasso(Request $request): Response
    {
        Gate::authorize('empresa.gerenciar');
        $empresa = $this->empresa($request);

        return Pdf::loadView('empresa.pdf.sicredi-passo-a-passo', [
            'empresa' => $empresa,
            'nomeParceiro' => $empresa->configuracaoGatewayPix()['sicredi']['csr_nome_parceiro'] ?: $this->certificados->nomeParceiroPadrao($empresa),
            'urlSistema' => route('empresa.edit', ['aba' => 'financeiro']),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->download('passo-a-passo-api-pix-sicredi.pdf');
    }

    protected function empresa(Request $request): Empresa
    {
        return Empresa::findOrFail($request->user()->empresa_id);
    }

    protected function voltar(): RedirectResponse
    {
        return redirect()->route('empresa.edit', ['aba' => 'financeiro']);
    }
}
