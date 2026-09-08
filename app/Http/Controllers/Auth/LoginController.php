<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credenciais = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credenciais, $request->boolean('lembrar'))) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Credenciais inválidas.',
            ]);
        }

        $user = Auth::user();

        if ($user->status !== 'ativo') {
            Auth::logout();

            // Mesma mensagem genérica de credenciais erradas — não confirmar
            // pra quem está tentando logar que aquele e-mail/senha existe e
            // está certo, só que a conta está desativada.
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Credenciais inválidas.',
            ]);
        }

        $request->session()->regenerate();
        $user->forceFill(['ultimo_login_at' => now(), 'ultimo_login_ip' => $request->ip()])->saveQuietly();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
