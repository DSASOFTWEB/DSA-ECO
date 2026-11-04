<?php

namespace App\Services\Hospedagem;

use App\Exceptions\NegocioException;
use App\Models\Hospedagem;
use App\Models\Quarto;
use App\Models\QuartoBloqueio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sincronização de calendário iCal por quarto com Booking.com/Airbnb:
 * - exportar(): o .ics que o canal lê (datas ocupadas no ERP, sem dados do hóspede);
 * - sincronizar(): lê o .ics do canal e mantém os QuartoBloqueio em dia
 *   (cria, atualiza datas, apaga os que o canal cancelou).
 * O iCal só informa "ocupado de X a Y" — não traz hóspede nem valor.
 */
class IcalQuartoService
{
    public function exportar(Quarto $quarto): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'erp';

        // Bloqueios importados não voltam para o canal (evita eco Booking → ERP → Booking).
        $hospedagens = Hospedagem::withoutGlobalScopes()
            ->where('quarto_id', $quarto->id)
            ->whereIn('status', ['reservado', 'hospedado'])
            ->whereDate('data_checkout_prevista', '>=', now()->subDay()->toDateString())
            ->orderBy('data_checkin_prevista')
            ->get(['id', 'data_checkin_prevista', 'data_checkout_prevista', 'updated_at']);

        $linhas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//DSA//ERP Pousada//PT-BR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escapar("Quarto {$quarto->numero}"),
        ];

        foreach ($hospedagens as $h) {
            array_push($linhas,
                'BEGIN:VEVENT',
                "UID:hospedagem-{$h->id}@{$host}",
                'DTSTAMP:'.($h->updated_at ?? now())->utc()->format('Ymd\THis\Z'),
                'DTSTART;VALUE=DATE:'.$h->data_checkin_prevista->format('Ymd'),
                'DTEND;VALUE=DATE:'.$h->data_checkout_prevista->format('Ymd'),
                'SUMMARY:Reservado',
                'TRANSP:OPAQUE',
                'END:VEVENT',
            );
        }

        $linhas[] = 'END:VCALENDAR';

        return implode("\r\n", $linhas)."\r\n";
    }

    /**
     * @return array{criados: int, atualizados: int, removidos: int, conflitos: int}
     */
    public function sincronizar(Quarto $quarto): array
    {
        $url = trim((string) $quarto->ical_importacao_url);
        if ($url === '') {
            throw new NegocioException('Informe o link do calendário do Booking.com neste quarto.');
        }

        try {
            $this->validarUrl($url);
            $resposta = Http::timeout(20)->connectTimeout(10)->withHeaders(['Accept' => 'text/calendar'])->get($url);
            if ($resposta->failed()) {
                throw new NegocioException("O calendário respondeu com erro (HTTP {$resposta->status()}). Confira o link no extranet.");
            }
            $corpo = $resposta->body();
            if (! str_contains($corpo, 'BEGIN:VCALENDAR')) {
                throw new NegocioException('O link não devolveu um calendário iCal (.ics). Copie de novo o link de exportação no extranet.');
            }
        } catch (NegocioException $e) {
            $quarto->forceFill(['ical_erro' => $e->getMessage()])->save();
            throw $e;
        } catch (\Throwable $e) {
            $mensagem = 'Não foi possível baixar o calendário: '.str($e->getMessage())->limit(150);
            $quarto->forceFill(['ical_erro' => $mensagem])->save();
            throw new NegocioException($mensagem);
        }

        $eventos = $this->lerEventos($corpo);
        $origem = $this->origemDaUrl($url);

        $resultado = DB::transaction(function () use ($quarto, $eventos, $origem) {
            $criados = $atualizados = $conflitos = 0;
            $existentes = QuartoBloqueio::withoutGlobalScopes()->where('quarto_id', $quarto->id)->get()->keyBy('uid');

            foreach ($eventos as $evento) {
                $bloqueio = $existentes->get($evento['uid']);
                $dados = [
                    'inicio' => $evento['inicio'],
                    'fim' => $evento['fim'],
                    'resumo' => $evento['resumo'],
                    'origem' => $origem,
                ];

                if (! $bloqueio) {
                    $bloqueio = QuartoBloqueio::withoutGlobalScopes()->create($dados + [
                        'empresa_id' => $quarto->empresa_id,
                        'quarto_id' => $quarto->id,
                        'uid' => $evento['uid'],
                    ]);
                    $criados++;
                } else {
                    $bloqueio->fill($dados);
                    if ($bloqueio->isDirty()) {
                        $bloqueio->save();
                        $atualizados++;
                    }
                }

                $conflito = $this->temConflito($bloqueio);
                if ($conflito !== $bloqueio->conflito) {
                    $bloqueio->forceFill(['conflito' => $conflito])->save();
                }
                $conflitos += (int) $conflito;
            }

            // Sumiu do canal = cancelado lá: libera o quarto no ERP.
            $removidos = QuartoBloqueio::withoutGlobalScopes()
                ->where('quarto_id', $quarto->id)
                ->whereNotIn('uid', array_column($eventos, 'uid') ?: ['__nenhum__'])
                ->delete();

            return compact('criados', 'atualizados', 'removidos', 'conflitos');
        });

        $quarto->forceFill(['ical_sincronizado_em' => now(), 'ical_erro' => null])->save();

        if ($resultado['conflitos'] > 0) {
            Log::warning('iCal: datas vendidas no canal batem com reserva do ERP (overbooking)', ['quarto_id' => $quarto->id] + $resultado);
        }

        return $resultado;
    }

    /**
     * Eventos (VEVENT) do .ics: datas de dia inteiro, "fim" exclusivo.
     * Cobre o formato do Booking.com/Airbnb (DTSTART;VALUE=DATE:20261110).
     *
     * @return list<array{uid: string, inicio: string, fim: string, resumo: ?string}>
     */
    public function lerEventos(string $ics): array
    {
        // Desdobra linhas continuadas (RFC 5545: quebra + espaço/tab).
        $ics = preg_replace("/\r?\n[ \t]/", '', $ics) ?? $ics;
        $eventos = [];
        $atual = null;

        foreach (preg_split("/\r?\n/", $ics) as $linha) {
            if ($linha === 'BEGIN:VEVENT') {
                $atual = [];

                continue;
            }
            if ($linha === 'END:VEVENT') {
                if ($atual !== null && ($evento = $this->normalizarEvento($atual))) {
                    $eventos[$evento['uid']] = $evento;
                }
                $atual = null;

                continue;
            }
            if ($atual === null || ! str_contains($linha, ':')) {
                continue;
            }

            [$chave, $valor] = explode(':', $linha, 2);
            $nome = strtoupper(explode(';', $chave)[0]);
            $atual[$nome] = $valor;
        }

        return array_values($eventos);
    }

    protected function normalizarEvento(array $e): ?array
    {
        if (strtoupper($e['STATUS'] ?? '') === 'CANCELLED' || empty($e['DTSTART'])) {
            return null;
        }

        $inicio = $this->data($e['DTSTART']);
        $fim = ! empty($e['DTEND']) ? $this->data($e['DTEND']) : null;
        if (! $inicio) {
            return null;
        }
        if (! $fim || $fim->lte($inicio)) {
            $fim = $inicio->copy()->addDay();
        }

        // Ignora o que já passou (o canal costuma mandar só o futuro, mas nem sempre).
        if ($fim->lt(now()->startOfDay())) {
            return null;
        }

        $resumo = isset($e['SUMMARY']) ? str_replace(['\\,', '\\;', '\\n', '\\N'], [',', ';', ' ', ' '], $e['SUMMARY']) : null;

        return [
            'uid' => mb_substr(trim($e['UID'] ?? md5($e['DTSTART'].($e['DTEND'] ?? '').($resumo ?? ''))), 0, 255),
            'inicio' => $inicio->toDateString(),
            'fim' => $fim->toDateString(),
            'resumo' => $resumo !== null ? mb_substr(trim($resumo), 0, 255) : null,
        ];
    }

    /** 20261110 ou 20261110T140000Z → dia. */
    protected function data(string $valor): ?Carbon
    {
        if (! preg_match('/^(\d{4})(\d{2})(\d{2})/', trim($valor), $m)) {
            return null;
        }

        return Carbon::createFromDate((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
    }

    /** Já tinha hóspede do ERP nessas datas quando o canal vendeu? */
    protected function temConflito(QuartoBloqueio $bloqueio): bool
    {
        return Hospedagem::withoutGlobalScopes()
            ->where('quarto_id', $bloqueio->quarto_id)
            ->whereIn('status', ['reservado', 'hospedado'])
            ->when($bloqueio->hospedagem_id, fn ($q, $id) => $q->where('id', '!=', $id))
            ->where('data_checkin_prevista', '<', $bloqueio->fim->toDateString())
            ->where('data_checkout_prevista', '>', $bloqueio->inicio->toDateString())
            ->exists();
    }

    public function origemDaUrl(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return match (true) {
            str_contains($host, 'booking.com') => 'booking',
            str_contains($host, 'airbnb.') => 'airbnb',
            default => 'ical',
        };
    }

    /**
     * Só HTTPS e só endereço público: o servidor baixa a URL digitada, então
     * não pode ser usada para alcançar a rede interna.
     */
    public function validarUrl(string $url): void
    {
        $partes = parse_url($url);
        if (($partes['scheme'] ?? '') !== 'https' || empty($partes['host'])) {
            throw new NegocioException('O link do calendário precisa começar com https://');
        }

        $ips = gethostbynamel($partes['host']) ?: [];
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new NegocioException('Endereço de calendário não permitido.');
            }
        }
    }

    protected function escapar(string $texto): string
    {
        return str_replace(['\\', ',', ';', "\n"], ['\\\\', '\\,', '\\;', '\\n'], $texto);
    }
}
