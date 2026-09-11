<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel não define nenhum header de segurança HTTP por padrão. Aplicado
 * globalmente ao grupo "web" (ver bootstrap/app.php).
 *
 * A CSP libera 'unsafe-inline' em script-src/style-src de propósito: o
 * projeto usa bastante <script>/<style> inline (PDV, checkout, landing) e
 * atributos style="" — trocar isso por nonce/hash é uma refatoração maior,
 * fora do escopo desta correção pontual. Mesmo assim a CSP já bloqueia a
 * classe de ataque mais comum (carregar script de um domínio externo).
 *
 * 'unsafe-eval' também é necessário: o Alpine.js (usado em praticamente
 * toda tela do painel — PDV, checkout, todo x-data/@click/x-show) avalia as
 * expressões dos atributos internamente via `new Function(...)`, o que o
 * navegador só permite com 'unsafe-eval' na CSP. Sem isso o Alpine falha
 * silenciosamente (sem erro visível na tela, só no console do navegador) e
 * a página inteira "trava" — nenhum clique/atalho responde.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Remover vazamento de stack (Apache/PHP).
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(self)');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; ".
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'; ".
            "style-src 'self' 'unsafe-inline'; ".
            "img-src 'self' data: blob:; ".
            "font-src 'self' data:; ".
            "connect-src 'self'; ".
            "frame-ancestors 'none'; ".
            "base-uri 'self'; ".
            "form-action 'self'"
        );

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
