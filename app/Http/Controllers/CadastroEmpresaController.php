<?php

namespace App\Http\Controllers;

use App\Http\Requests\CadastroEmpresaRequest;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Autocadastro de uma nova empresa (tenant) do SaaS: o próprio dono do
 * parque cria a empresa, a primeira unidade e o usuário administrador, sem
 * depender de um super_admin cadastrar manualmente. Roles/permissões já
 * existem globalmente (seed de instalação), então basta atribuir 'admin'.
 */
class CadastroEmpresaController extends Controller
{
    public function create(): View
    {
        return view('publico.cadastro');
    }

    public function store(CadastroEmpresaRequest $request): RedirectResponse
    {
        $dados = $request->validated();

        $user = DB::transaction(function () use ($dados) {
            $empresa = Empresa::create([
                'nome' => $dados['empresa_nome'],
                'cnpj' => $dados['empresa_cnpj'] ?? null,
                'telefone' => $dados['empresa_telefone'] ?? null,
                'email' => $dados['admin_email'],
                'plano_saas' => 'padrao',
                'status' => 'ativo',
                'trial_termina_em' => now()->addDays(14),
            ]);

            $unidade = Unidade::create([
                'empresa_id' => $empresa->id,
                'nome' => $dados['unidade_nome'],
                'status' => 'ativo',
            ]);

            $user = User::create([
                'empresa_id' => $empresa->id,
                'unidade_id' => $unidade->id,
                'name' => $dados['admin_nome'],
                'email' => $dados['admin_email'],
                'password' => Hash::make($dados['admin_password']),
                'status' => 'ativo',
            ]);
            $user->syncRoles(['admin']);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('sucesso', "Conta criada com sucesso! Bem-vindo(a), {$user->name}. Você tem 14 dias de teste grátis.");
    }
}
