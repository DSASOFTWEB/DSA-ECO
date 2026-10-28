<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Dependente;
use App\Services\ClienteService;
use App\Support\Documento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DependenteController extends Controller
{
    public function __construct(protected ClienteService $clienteService) {}

    public function store(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->authorize('create', Dependente::class);

        $dados = $this->normalizar($request->validate($this->regras()));

        $this->clienteService->adicionarDependente($cliente, $dados);

        return redirect()->route('clientes.show', $cliente)->with('sucesso', 'Dependente adicionado com sucesso.');
    }

    /**
     * Corrige os dados do dependente. A carteirinha acompanha sozinha (o
     * nome e a foto são lidos do cadastro na hora de exibir/imprimir).
     */
    public function update(Request $request, Cliente $cliente, Dependente $dependente): RedirectResponse
    {
        abort_unless((int) $dependente->cliente_id === (int) $cliente->id, 404);
        $this->authorize('update', $dependente);

        // Bag própria: se der erro, reabre só o formulário deste dependente.
        $dados = $this->normalizar($request->validateWithBag("dependente{$dependente->id}", $this->regras($dependente)));

        $dependente->update($dados);

        return redirect()->route('clientes.show', $cliente)->with('sucesso', "Dependente {$dependente->nome} atualizado.");
    }

    public function destroy(Cliente $cliente, Dependente $dependente): RedirectResponse
    {
        abort_unless((int) $dependente->cliente_id === (int) $cliente->id, 404);
        $this->authorize('delete', $dependente);

        $dependente->delete();

        return redirect()->route('clientes.show', $cliente)->with('sucesso', 'Dependente removido.');
    }

    /** CPF guardado só com dígitos (igual ao cliente); vazio vira null. */
    protected function normalizar(array $dados): array
    {
        $dados['cpf'] = filled($dados['cpf'] ?? null) ? Documento::somenteDigitos($dados['cpf']) : null;

        return $dados;
    }

    protected function regras(?Dependente $dependente = null): array
    {
        // Cadastro antigo com parentesco fora da lista (e não reconhecido):
        // continua aceito para este dependente, para não forçar troca ao editar.
        $parentescos = Dependente::niveisParentesco();
        if ($dependente && filled($dependente->parentesco) && $dependente->parentescoNaLista() === null) {
            $parentescos[] = $dependente->parentesco;
        }

        return [
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:14', function ($atributo, $valor, $falhar) {
                if (filled($valor) && ! Documento::cpfValido($valor)) {
                    $falhar('CPF inválido. Confira os números ou deixe em branco.');
                }
            }],
            'data_nascimento' => ['required', 'date', 'before_or_equal:today'],
            'parentesco' => ['required', 'string', 'max:40', Rule::in($parentescos)],
        ];
    }
}
