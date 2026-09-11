<?php

use App\Http\Middleware\IdentificaEmpresaAtual;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Habilita autenticação stateful (cookie) do Sanctum para chamadas
        // do próprio painel admin ao /api/*, além do Bearer token do app mobile.
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Garante que toda query do usuário autenticado fique restrita à
        // empresa (tenant) e, quando aplicável, à unidade dele.
        $middleware->web(append: [
            IdentificaEmpresaAtual::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Host permitido = domínio do APP_URL + localhost (healthcheck Docker
        // faz curl em http://localhost/...; sem isso o TrustHosts devolve 400
        // e o container fica "unhealthy" mesmo com a app no ar).
        $middleware->trustHosts(at: function () {
            $hosts = ['localhost', '127.0.0.1'];
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            if (is_string($appHost) && $appHost !== '') {
                $hosts[] = $appHost;
            }

            return array_values(array_unique($hosts));
        });

        // Proxies: rede Docker + Cloudflare.
        // IMPORTANTE: não usar config() aqui — o withMiddleware roda antes do
        // repositório de config estar no container (fatal: Class "config").
        // TRUSTED_PROXIES=* só se a origem não for pública.
        $trustedRaw = (string) env(
            'TRUSTED_PROXIES',
            '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,'.
            '173.245.48.0/20,103.21.244.0/22,103.22.200.0/22,103.31.4.0/22,'.
            '141.101.64.0/18,108.162.192.0/18,190.93.240.0/20,188.114.96.0/20,'.
            '197.234.240.0/22,198.41.128.0/17,162.158.0.0/15,104.16.0.0/13,'.
            '104.24.0.0/14,172.64.0.0/13,131.0.72.0/22'
        );
        $trusted = array_values(array_filter(array_map('trim', explode(',', $trustedRaw))));
        $trustAll = $trusted === ['*'];
        $middleware->trustProxies(
            at: $trustAll ? '*' : $trusted,
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Regra de negócio violada (ex.: "plano permite no máximo N
        // dependentes") nunca deve virar página de erro 500 — mesmo quando o
        // controller que a disparou esqueceu de capturá-la localmente. Rede
        // de segurança global: sempre volta pra tela anterior com a mensagem
        // amigável, em vez do stack trace.
        $exceptions->render(function (\App\Exceptions\NegocioException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('erro', $e->getMessage());
        });
    })->create();
