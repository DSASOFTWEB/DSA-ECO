<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Services\Integrations\Pix\ItauCertificadoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza certificado mTLS Itaú (banco ↔ disco), limpa cache de token
 * OAuth e alerta se a validade estiver perto do fim.
 *
 * A renovação do certificado dinâmico em si continua no portal Itaú
 * (CSR + token temporário); este comando evita perda no deploy e avisa
 * com antecedência para o operador reenviar o .crt/.key.
 */
class ItauManutencaoCertificadosCommand extends Command
{
    protected $signature = 'pix:itau-manutencao-certificados';

    protected $description = 'Sincroniza certificados Pix Itaú (banco/disco), limpa token OAuth e alerta validade';

    public function handle(ItauCertificadoService $certs): int
    {
        $empresas = Empresa::query()->get();
        $sincronizadas = 0;
        $alertas = 0;
        $semPar = 0;

        foreach ($empresas as $empresa) {
            $provedor = $empresa->configuracaoGatewayPix()['provedor'];
            $temAlgo = $certs->temCertificado($empresa)
                || filled($empresa->configuracaoGatewayPix()['itau']['client_id'] ?? null);

            if ($provedor !== Empresa::GATEWAY_PIX_ITAU && ! $temAlgo) {
                continue;
            }

            if (! $certs->garantirArquivos($empresa)) {
                if ($provedor === Empresa::GATEWAY_PIX_ITAU) {
                    $semPar++;
                    Log::warning('Itaú Pix: empresa sem certificado no banco nem no disco', [
                        'empresa_id' => $empresa->id,
                    ]);
                }
                continue;
            }

            $empresa->refresh();
            $certs->limparCacheToken($empresa);
            $sincronizadas++;

            $validade = $empresa->configuracaoGatewayPix()['itau']['certificado_validade'];
            if (! $validade) {
                $doBanco = $certs->lerDoBanco($empresa);
                $validade = $doBanco['validade'];
                if ($validade && filled($doBanco['certificado']) && filled($doBanco['chave_privada'])) {
                    // Atualiza metadados de validade se ainda não estavam no JSON.
                    $certs->persistirNoBanco($empresa, $doBanco['certificado'], $doBanco['chave_privada']);
                }
            }

            if ($validade && ($validade->isPast() || $validade->lte(now()->addDays(30)))) {
                $alertas++;
                Log::warning('Itaú Pix: certificado próximo do vencimento ou expirado — renove no portal', [
                    'empresa_id' => $empresa->id,
                    'validade' => $validade->toDateString(),
                    'dias' => $validade->isPast() ? 0 : (int) now()->diffInDays($validade),
                ]);
                $this->warn("Empresa #{$empresa->id}: certificado vence em {$validade->format('d/m/Y')}");
            }
        }

        $this->info("Sincronizadas: {$sincronizadas} | Alertas de validade: {$alertas} | Sem par: {$semPar}");

        Log::info('Itaú Pix: manutenção mensal de certificados concluída', [
            'sincronizadas' => $sincronizadas,
            'alertas' => $alertas,
            'sem_par' => $semPar,
        ]);

        return self::SUCCESS;
    }
}
