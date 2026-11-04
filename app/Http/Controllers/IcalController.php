<?php

namespace App\Http\Controllers;

use App\Models\Quarto;
use App\Services\Hospedagem\IcalQuartoService;
use Illuminate\Http\Response;

/**
 * Calendário .ics público de um quarto, lido pelo Booking.com/Airbnb.
 * Sem login: o token secreto do link é a autorização (gerar um novo link
 * no quarto invalida o anterior). Só expõe datas ocupadas, sem hóspede.
 */
class IcalController extends Controller
{
    public function quarto(string $token, IcalQuartoService $ical): Response
    {
        $quarto = Quarto::withoutGlobalScopes()->where('ical_token', $token)->firstOrFail();

        return response($ical->exportar($quarto), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="quarto-'.$quarto->id.'.ics"',
            'Cache-Control' => 'no-cache, max-age=0',
        ]);
    }
}
