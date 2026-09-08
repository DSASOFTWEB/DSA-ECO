<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Fluxo padrão do Laravel (Illuminate\Auth\Passwords\PasswordBroker) para
 * recuperação de senha por e-mail — antes disso não existia nenhum caminho
 * oficial pra um usuário travado recuperar a conta sozinho. Mensagens
 * sempre genéricas (nunca confirmam se o e-mail existe ou não), mesmo
 * padrão anti-enumeração já usado no login.
 */
class PasswordResetController extends Controller
{
    public function showLinkRequestForm(): View
    {
        return view('auth.esqueci-senha');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput()->with('erro', 'Você já pediu um link há pouco. Aguarde alguns minutos e tente de novo.');
        }

        return back()->with('sucesso', 'Se existir uma conta com esse e-mail, enviamos um link de redefinição de senha.');
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.redefinir-senha', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset($dados, function ($user, $password) {
            $user->forceFill(['password' => $password])->save();

            // Revoga qualquer token de API (app mobile) existente — se a
            // senha precisou ser redefinida, sessões já abertas em outros
            // dispositivos não devem continuar valendo silenciosamente.
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('sucesso', 'Senha redefinida com sucesso! Faça login com a nova senha.');
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
