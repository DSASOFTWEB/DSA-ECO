<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Login (web e API mobile) e autocadastro de empresa não tinham
        // nenhum limite de tentativas — força bruta/credential stuffing
        // ilimitado. Limita por e-mail+IP (não só IP, pra não travar um
        // WI-FI/NAT compartilhado inteiro por causa de uma pessoa) e também
        // por IP sozinho (rede de segurança contra e-mail variável).
        RateLimiter::for('login', function (Request $request) {
            $chave = mb_strtolower((string) $request->input('email')).'|'.$request->ip();

            return [
                Limit::perMinute(5)->by($chave),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('cadastro-empresa', function (Request $request) {
            return Limit::perMinutes(10, 5)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinutes(10, 5)->by($request->ip());
        });
    }
}
