<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::with(['unidade', 'roles'])->orderBy('name')->paginate(20);

        return view('usuarios.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        $unidades = Unidade::ativas()->get();
        $roles = Role::where('name', '!=', 'super_admin')->orderBy('name')->get();

        return view('usuarios.create', compact('unidades', 'roles'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $roles = $dados['roles'];
        unset($dados['roles']);

        // Só quem já é admin do tenant pode promover outra pessoa a admin —
        // sem isso, qualquer papel com "usuarios.criar" (ex.: gerente)
        // poderia se autoconceder/conceder o papel mais alto do tenant.
        if (in_array('admin', $roles, true)) {
            $this->authorize('atribuirAdmin', User::class);
        }

        $dados['password'] = Hash::make($dados['password']);
        $dados['empresa_id'] = $request->user()->empresa_id;

        $user = User::create($dados);
        $user->syncRoles($roles);

        return redirect()->route('usuarios.index')->with('sucesso', "Usuário \"{$user->name}\" criado com sucesso.");
    }

    /**
     * O parâmetro precisa se chamar $usuario (não $user) — a rota gerada por
     * Route::resource('usuarios', ...) usa {usuario}, e o model binding
     * implícito do Laravel casa o parâmetro do model pelo NOME, não só pelo
     * tipo. Com nomes diferentes, o Laravel não lançava erro nenhum: só
     * injetava um User vazio (novo, sem dados) em vez do usuário de verdade,
     * e a Policy negava tudo (comparava empresa_id null com null !== nada).
     */
    public function edit(User $usuario): View
    {
        $this->authorize('update', $usuario);

        $unidades = Unidade::ativas()->get();
        $roles = Role::where('name', '!=', 'super_admin')->orderBy('name')->get();

        return view('usuarios.edit', ['user' => $usuario, 'unidades' => $unidades, 'roles' => $roles]);
    }

    public function update(UpdateUserRequest $request, User $usuario): RedirectResponse
    {
        $dados = $request->validated();
        $roles = $dados['roles'] ?? null;
        unset($dados['roles']);

        if (! empty($dados['password'])) {
            $dados['password'] = Hash::make($dados['password']);
            // Senha trocada por quem edita (não pela própria pessoa) — revoga
            // tokens de API já emitidos, mesma cautela do "esqueci a senha".
            $usuario->tokens()->delete();
        } else {
            unset($dados['password']);
        }

        $usuario->update($dados);

        if ($roles !== null) {
            $this->authorize('gerenciarPapeis', $usuario);

            if (in_array('admin', $roles, true)) {
                $this->authorize('atribuirAdmin', User::class);
            }

            $usuario->syncRoles($roles);
        }

        return redirect()->route('usuarios.index')->with('sucesso', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $usuario): RedirectResponse
    {
        $this->authorize('delete', $usuario);

        $usuario->update(['status' => 'inativo']);

        return redirect()->route('usuarios.index')->with('sucesso', 'Usuário inativado.');
    }
}
