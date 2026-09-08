<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Roda em toda requisição web autenticada. Responsabilidades:
 *  1. Bloquear o acesso se a empresa (tenant) do usuário estiver
 *     suspensa/cancelada — evita que um cliente inadimplente do SaaS
 *     continue usando o sistema mesmo autenticado.
 *  2. Compartilhar a empresa/unidade atuais com as views, para uso em
 *     layouts (nome do parque no cabeçalho, seletor de unidade etc.).
 *
 * O isolamento dos DADOS em si (queries filtradas por empresa_id) já é
 * garantido pelo TenantScope aplicado nos models via BelongsToTenant —
 * este middleware cuida da experiência/segurança de nível de aplicação,
 * não substitui o escopo.
 */
class IdentificaEmpresaAtual
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            $empresa = $user->empresa;

            if ($empresa && in_array($empresa->status, ['suspenso', 'cancelado'], true)) {
                Auth::logout();

                return redirect()->route('login')
                    ->withErrors(['email' => 'O acesso da sua empresa está temporariamente suspenso. Entre em contato com o suporte.']);
            }

            view()->share('empresaAtual', $empresa);
            view()->share('unidadeAtual', $user->unidade);
        }

        return $next($request);
    }
}
