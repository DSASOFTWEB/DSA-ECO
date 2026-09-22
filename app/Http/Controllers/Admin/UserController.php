<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Unidade;
use App\Models\User;
use App\Support\ModulosPermissoes;
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
        $roles = $dados['roles'] ?? [];
        $permissions = $dados['permissions'] ?? [];
        unset($dados['roles'], $dados['permissions']);

        if (in_array('admin', $roles, true)) {
            $this->authorize('atribuirAdmin', User::class);
        }

        $dados['password'] = Hash::make($dados['password']);
        $dados['empresa_id'] = $request->user()->empresa_id;

        $user = User::create($dados);
        $this->sincronizarAcesso($user, $roles, $permissions);

        return redirect()->route('usuarios.index')->with('sucesso', "Usuário \"{$user->name}\" criado com sucesso.");
    }

    public function edit(User $usuario): View
    {
        abort_unless($usuario->exists, 404);

        $this->authorize('update', $usuario);

        $unidades = Unidade::ativas()->get();
        $roles = Role::where('name', '!=', 'super_admin')->orderBy('name')->get();

        return view('usuarios.edit', ['user' => $usuario, 'unidades' => $unidades, 'roles' => $roles]);
    }

    public function update(UpdateUserRequest $request, User $usuario): RedirectResponse
    {
        abort_unless($usuario->exists, 404);

        $dados = $request->validated();
        $roles = array_key_exists('roles', $dados) ? ($dados['roles'] ?? []) : null;
        $permissions = array_key_exists('permissions', $dados) ? ($dados['permissions'] ?? []) : null;
        unset($dados['roles'], $dados['permissions']);

        if (! empty($dados['password'])) {
            $dados['password'] = Hash::make($dados['password']);
            $usuario->tokens()->delete();
        } else {
            unset($dados['password']);
        }

        $usuario->update($dados);

        if ($roles !== null || $permissions !== null) {
            $this->authorize('gerenciarPapeis', $usuario);

            $roles ??= $usuario->getRoleNames()->all();
            $permissions ??= $usuario->getAllPermissions()->pluck('name')->all();

            if (in_array('admin', $roles, true)) {
                $this->authorize('atribuirAdmin', User::class);
            }

            $this->sincronizarAcesso($usuario, $roles, $permissions);
        }

        return redirect()->route('usuarios.index')->with('sucesso', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $usuario): RedirectResponse
    {
        abort_unless($usuario->exists, 404);

        $this->authorize('delete', $usuario);

        $usuario->update(['status' => 'inativo']);

        return redirect()->route('usuarios.index')->with('sucesso', 'Usuário inativado.');
    }

    /**
     * Perfis (roles) + matriz de módulos. As permissões marcadas na matriz
     * são gravadas no usuário; se só veio perfil, aplica o pacote padrão.
     *
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    protected function sincronizarAcesso(User $user, array $roles, array $permissions): void
    {
        $permissions = array_values(array_intersect(
            array_unique($permissions),
            ModulosPermissoes::todasPermissoes()
        ));

        if ($permissions === [] && $roles !== []) {
            foreach ($roles as $papel) {
                foreach (ModulosPermissoes::permissoesPorPapel()[$papel] ?? [] as $perm) {
                    $permissions[] = $perm;
                }
            }
            $permissions = array_values(array_unique($permissions));
        }

        $user->syncRoles($roles);
        $user->syncPermissions($permissions);
    }
}
