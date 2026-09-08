<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Landing page pública do SaaS. Fica no grupo de rotas "guest" — quem já
 * está logado é automaticamente redirecionado para o painel, então esta
 * tela só existe para visitante/lead conhecer o produto e criar a conta.
 */
class PaginaInicialController extends Controller
{
    public function __invoke(): View
    {
        return view('publico.landing');
    }
}
