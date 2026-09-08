<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Cliente;
use App\Models\Dependente;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClienteService
{
    public function __construct(
        protected ClienteRepositoryInterface $clientes,
        protected CarteirinhaService $carteirinhaService,
    ) {}

    public function listar(array $filtros, int $porPagina = 15)
    {
        return $this->clientes->paginate($porPagina, ['unidade', 'contratoAtivo'], $filtros);
    }

    public function criar(array $dados, ?UploadedFile $foto = null): Cliente
    {
        $this->garantirCpfUnico($dados['cpf']);

        unset($dados['foto']);

        if ($foto) {
            $dados['foto_path'] = $foto->store('clientes', 'public');
        }

        return DB::transaction(fn () => $this->clientes->create($dados));
    }

    public function atualizar(Cliente $cliente, array $dados, ?UploadedFile $foto = null): Cliente
    {
        if (($dados['cpf'] ?? $cliente->cpf) !== $cliente->cpf) {
            $this->garantirCpfUnico($dados['cpf'], ignorarId: $cliente->id);
        }

        unset($dados['foto']);

        if ($foto) {
            if ($cliente->foto_path) {
                Storage::disk('public')->delete($cliente->foto_path);
            }

            $dados['foto_path'] = $foto->store('clientes', 'public');
        }

        return DB::transaction(fn () => $this->clientes->update($cliente, $dados));
    }

    public function inativar(Cliente $cliente): Cliente
    {
        if ($cliente->contratos()->where('status', 'ativo')->exists()) {
            throw new NegocioException('Não é possível inativar um cliente com contrato ativo. Cancele o contrato primeiro.');
        }

        return $this->clientes->update($cliente, ['status' => 'inativo']);
    }

    /**
     * Cria o dependente e, se o cliente tiver um contrato ativo, já o
     * vincula a esse contrato (tabela contrato_dependente) e emite a
     * carteirinha dele — mesmo comportamento de quando os dependentes são
     * informados na hora de contratar (ver ContratoService::contratar()),
     * só que para quando o dependente é adicionado depois, na tela do cliente.
     */
    public function adicionarDependente(Cliente $cliente, array $dados): Dependente
    {
        $contratoAtivo = $cliente->contratoAtivo;
        $limiteDoContrato = $contratoAtivo?->plano?->max_dependentes;

        if ($limiteDoContrato !== null) {
            $totalAtual = $cliente->dependentes()->count();

            if ($totalAtual >= $limiteDoContrato) {
                throw new NegocioException("O plano do cliente permite no máximo {$limiteDoContrato} dependente(s).");
            }
        }

        return DB::transaction(function () use ($cliente, $dados, $contratoAtivo) {
            $dependente = $cliente->dependentes()->create($dados);

            if ($contratoAtivo) {
                $contratoAtivo->dependentes()->attach($dependente->id);
                $this->carteirinhaService->emitirParaDependente($dependente);
            }

            return $dependente;
        });
    }

    protected function garantirCpfUnico(string $cpf, ?int $ignorarId = null): void
    {
        $existente = $this->clientes->buscarPorCpf($cpf);

        if ($existente && $existente->id !== $ignorarId) {
            throw new NegocioException('Já existe um cliente cadastrado com este CPF.');
        }
    }
}
