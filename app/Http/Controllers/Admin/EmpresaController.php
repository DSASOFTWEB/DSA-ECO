<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\IntegrationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Empresa\UpdateEmpresaRequest;
use App\Models\Empresa;
use App\Models\PontoAtendimento;
use App\Models\Unidade;
use App\Services\EmpresaService;
use App\Services\Integrations\CnpjConsultaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dados cadastrais da própria empresa (nome, CNPJ, endereço e logo) — não é
 * um CRUD de várias empresas (isso é operação SaaS interna da DSA), é a
 * tela onde CADA empresa cliente edita os próprios dados. Por isso não há
 * index/create/destroy: sempre edita a empresa do usuário logado.
 */
class EmpresaController extends Controller
{
    public function __construct(protected EmpresaService $empresaService) {}

    public function edit(): View
    {
        Gate::authorize('empresa.gerenciar');

        $empresa = Empresa::findOrFail(request()->user()->empresa_id);
        $unidades = Unidade::ativas()->orderBy('nome')->get();
        $unidadeFoodId = (int) request()->integer('unidade_id')
            ?: (request()->user()->unidade_id ?: $unidades->first()?->id);
        $tipoFood = in_array(request()->input('tipo'), ['mesa', 'comanda'], true)
            ? request()->input('tipo')
            : 'mesa';
        $pontosFood = $unidadeFoodId
            ? PontoAtendimento::query()
                ->where('unidade_id', $unidadeFoodId)
                ->where('tipo', $tipoFood)
                ->orderBy('ordem')
                ->orderBy('numero')
                ->get()
            : collect();

        return view('empresa.edit', compact('empresa', 'unidades', 'unidadeFoodId', 'tipoFood', 'pontosFood'));
    }

    public function consultarCnpj(Request $request, CnpjConsultaService $cnpj): JsonResponse
    {
        Gate::authorize('empresa.gerenciar');

        $request->validate([
            'cnpj' => ['required', 'string', 'max:18'],
        ]);

        try {
            return response()->json($cnpj->consultar((string) $request->query('cnpj')));
        } catch (IntegrationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function update(UpdateEmpresaRequest $request): RedirectResponse
    {
        $empresa = Empresa::findOrFail($request->user()->empresa_id);

        $this->empresaService->atualizar(
            $empresa,
            $request->safe()->except(['logo', 'certificado']),
            $request->file('logo'),
            $request->file('certificado'),
        );

        return redirect()->route('empresa.edit')->with('sucesso', 'Dados da empresa atualizados com sucesso.');
    }

    public function downloadCertificado(): StreamedResponse|Response
    {
        Gate::authorize('empresa.gerenciar');

        $empresa = Empresa::findOrFail(request()->user()->empresa_id);
        abort_unless($empresa->temCertificadoDigital(), 404, 'Certificado digital não cadastrado.');

        $conteudo = $empresa->getRawOriginal('certificado_arquivo');
        $cnpj = preg_replace('/\D+/', '', (string) $empresa->cnpj) ?: 'empresa';

        return response()->streamDownload(
            function () use ($conteudo) {
                echo $conteudo;
            },
            "certificado-{$cnpj}.pfx",
            ['Content-Type' => 'application/x-pkcs12']
        );
    }
}
