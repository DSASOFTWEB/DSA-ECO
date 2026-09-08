<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler diário do módulo financeiro / acesso
|--------------------------------------------------------------------------
| A ordem importa: primeiro geramos as mensalidades do dia, depois
| marcamos como atrasadas as que já venceram (de dias anteriores),
| então disparamos a régua de cobrança e, por fim, aplicamos os
| bloqueios de acesso por inadimplência — cada etapa depende do
| resultado da anterior estar consistente no banco.
|
| Em produção, lembre-se de manter um `php artisan schedule:work` (ou o
| cron `* * * * * php artisan schedule:run`) rodando, além de ao menos
| um `php artisan queue:work` para consumir os Jobs enfileirados.
*/
Schedule::command('mensalidades:gerar')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('mensalidades:aplicar-atrasos')
    ->dailyAt('01:15')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('mensalidades:cobrar')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('inadimplencia:processar')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('carteirinhas:expirar')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('financeiro:aplicar-atrasos')
    ->dailyAt('01:20')
    ->withoutOverlapping()
    ->onOneServer();
