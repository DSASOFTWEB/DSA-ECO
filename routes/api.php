<?php

use App\Http\Controllers\Api\AcessoController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Webhooks\EvolutionApiWebhookController;
use App\Http\Controllers\Webhooks\MercadoPagoWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhooks públicos (sem autenticação de usuário — validados por
| assinatura/segredo do próprio provedor). Ficam fora do prefixo /api/v1
| deliberadamente, pois a URL é cadastrada manualmente nos painéis do
| Mercado Pago e da Evolution API.
|--------------------------------------------------------------------------
*/
Route::post('webhooks/mercadopago', MercadoPagoWebhookController::class)->middleware('throttle:60,1')->name('webhooks.mercadopago');
Route::post('webhooks/evolution', EvolutionApiWebhookController::class)->middleware('throttle:60,1')->name('webhooks.evolution');

/*
|--------------------------------------------------------------------------
| API v1 — app mobile (equipe do parque) e terminal de controle de acesso
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::get('clientes', [ClienteController::class, 'index']);
        Route::get('clientes/{cliente}', [ClienteController::class, 'show']);

        // Chamado pela catraca/totem físico (usuário de serviço com a
        // permissão "acessos.validar" — ver CarteirinhaPolicy).
        Route::post('acessos/validar', [AcessoController::class, 'validar']);
    });
});
