<?php

namespace App\Services;

use App\Exceptions\IntegrationException;
use App\Models\Cobranca;
use App\Models\Mensalidade;
use App\Notifications\Channels\WhatsappChannel;
use App\Notifications\CobrancaMensalidadeNotification;
use App\Services\Integrations\EvolutionApiService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fila das cobranças de mensalidade por WhatsApp. Disparar muitas mensagens
 * seguidas leva o WhatsApp a banir o número, então nada é enviado na hora:
 * régua diária e botões só AGENDAM (Cobranca status "agendado") e o
 * enviador (comando a cada minuto) manda no máximo 1 por empresa a cada
 * N minutos, só no horário comercial, sem reenvio automático em falha.
 */
class CobrancaWhatsappFila
{
    public const AGENDADO = 'agendado';

    public const ORIGEM_REGUA = 'Régua automática';

    /**
     * Põe a mensalidade na fila. Não duplica: se já há uma agendada para ela
     * (ou, na régua, a mesma etapa já saiu hoje), devolve null.
     */
    public function agendar(Mensalidade $mensalidade, int $tentativa = 1, bool $daRegua = false): ?Cobranca
    {
        if ($mensalidade->estaPaga() || in_array($mensalidade->status, ['cancelado', 'isento'], true)) {
            return null;
        }

        $jaNaFila = Cobranca::where('mensalidade_id', $mensalidade->id)->where('canal', 'whatsapp')
            ->where(fn ($q) => $q->where('status', self::AGENDADO)
                ->when($daRegua, fn ($r) => $r->orWhere(fn ($h) => $h->where('tentativa', $tentativa)->whereDate('created_at', now()->toDateString()))))
            ->exists();

        if ($jaNaFila) {
            return null;
        }

        return Cobranca::create([
            'mensalidade_id' => $mensalidade->id,
            'canal' => 'whatsapp',
            'status' => self::AGENDADO,
            'tentativa' => $tentativa,
            // Marca a origem: se a empresa desligar a cobrança automática, as da régua não saem.
            'mensagem' => $daRegua ? self::ORIGEM_REGUA : null,
        ]);
    }

    /**
     * Envia a próxima da fila de cada empresa, respeitando o intervalo e o
     * horário. Devolve quantas mensagens saíram nesta rodada.
     */
    public function enviarProximas(?Carbon $agora = null): int
    {
        $agora ??= now();
        if (! $this->dentroDoHorario($agora)) {
            return 0;
        }

        $empresas = Cobranca::query()
            ->join('mensalidades', 'mensalidades.id', '=', 'cobrancas.mensalidade_id')
            ->where('cobrancas.canal', 'whatsapp')->where('cobrancas.status', self::AGENDADO)
            ->distinct()->pluck('mensalidades.empresa_id');

        $enviadas = 0;
        foreach ($empresas as $empresaId) {
            if ($this->aguardandoIntervalo((int) $empresaId, $agora)) {
                continue;
            }

            $proxima = Cobranca::query()->select('cobrancas.*')
                ->join('mensalidades', 'mensalidades.id', '=', 'cobrancas.mensalidade_id')
                ->where('mensalidades.empresa_id', $empresaId)
                ->where('cobrancas.canal', 'whatsapp')->where('cobrancas.status', self::AGENDADO)
                ->orderBy('cobrancas.id')->first();

            if ($proxima && $this->enviar($proxima)) {
                $enviadas++;
            }
        }

        return $enviadas;
    }

    /**
     * Envia uma cobrança agendada (uma tentativa só). true = gastou a vez da
     * empresa (enviou ou tentou); false = cancelada sem tocar no WhatsApp.
     */
    public function enviar(Cobranca $cobranca): bool
    {
        // Trava a linha: dois enviadores ao mesmo tempo não mandam a mesma.
        $cobranca = DB::transaction(function () use ($cobranca) {
            $travada = Cobranca::whereKey($cobranca->id)->lockForUpdate()->first();
            if (! $travada || $travada->status !== self::AGENDADO) {
                return null;
            }
            $travada->update(['status' => 'enviando']);

            return $travada;
        });

        if (! $cobranca) {
            return false;
        }

        $mensalidade = Mensalidade::withoutGlobalScopes()->with('contrato.cliente.empresa')->find($cobranca->mensalidade_id);
        $cliente = $mensalidade?->contrato?->cliente;

        if (! $mensalidade || $mensalidade->estaPaga() || in_array($mensalidade->status, ['cancelado', 'isento'], true)) {
            $cobranca->update(['status' => 'cancelado', 'mensagem' => 'Mensalidade paga/cancelada antes do envio.']);

            return false;
        }

        // Veio da régua e a empresa desligou a cobrança automática depois de agendar.
        if ($cobranca->mensagem === self::ORIGEM_REGUA && $cliente?->empresa && ! $cliente->empresa->cobrancaAutomaticaWhatsapp()) {
            $cobranca->update(['status' => 'cancelado', 'mensagem' => 'Cobrança automática desligada em Minha Empresa → Gerencial.']);

            return false;
        }

        if (! $cliente?->routeNotificationForWhatsapp()) {
            $cobranca->update(['status' => 'falhou', 'mensagem' => 'Cliente sem número de WhatsApp/telefone cadastrado.']);

            return false;
        }

        try {
            $resposta = app(WhatsappChannel::class)->send($cliente, new CobrancaMensalidadeNotification($mensalidade));
            $cobranca->update(['status' => 'enviado', 'enviado_em' => now(), 'resposta_gateway' => $resposta, 'mensagem' => null]);
        } catch (IntegrationException $e) {
            $motivo = EvolutionApiService::paraEmpresa($cliente->empresa)->explicar($e)->getMessage();
            $cobranca->update(['status' => 'falhou', 'mensagem' => $motivo]);
            Log::warning('Cobrança por WhatsApp não enviada (sem reenvio automático)', ['cobranca_id' => $cobranca->id, 'erro' => $motivo]);
        } catch (\Throwable $e) {
            $cobranca->update(['status' => 'falhou', 'mensagem' => mb_substr($e->getMessage(), 0, 500)]);
            report($e);
        }

        return true;
    }

    public function dentroDoHorario(Carbon $agora): bool
    {
        $hora = (int) $agora->format('G');

        return $hora >= (int) config('parque.cobranca_janela_inicio', 8)
            && $hora < (int) config('parque.cobranca_janela_fim', 20);
    }

    /** A última tentativa da empresa foi há menos que o intervalo? */
    protected function aguardandoIntervalo(int $empresaId, Carbon $agora): bool
    {
        $ultima = Cobranca::query()
            ->join('mensalidades', 'mensalidades.id', '=', 'cobrancas.mensalidade_id')
            ->where('mensalidades.empresa_id', $empresaId)
            ->where('cobrancas.canal', 'whatsapp')
            ->whereIn('cobrancas.status', ['enviado', 'falhou', 'enviando', 'lido', 'respondido'])
            ->max('cobrancas.updated_at');

        return $ultima && Carbon::parse($ultima)->gt($agora->copy()->subMinutes((int) config('parque.cobranca_intervalo_minutos', 20)));
    }

    /** Posição/estimativa para a tela: quantas estão na fila da empresa. */
    public function naFila(int $empresaId): int
    {
        return Cobranca::query()
            ->join('mensalidades', 'mensalidades.id', '=', 'cobrancas.mensalidade_id')
            ->where('mensalidades.empresa_id', $empresaId)
            ->where('cobrancas.canal', 'whatsapp')->where('cobrancas.status', self::AGENDADO)
            ->count();
    }
}
