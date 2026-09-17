<?php

namespace App\Services\Fiscal;

use App\Exceptions\IntegrationException;
use App\Exceptions\NegocioException;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\Unidade;
use Illuminate\Support\Facades\Http;
use NFePHP\Common\Signer;

/**
 * Emite NFS-e Nacional (DPS) para diárias/serviços da hospedagem.
 * Fluxo alinhado ao Evora (SEFIN Nacional + mTLS do A1).
 */
class NfseEmissaoService
{
    public function __construct(protected CertificadoA1Service $certificadoA1) {}

    /**
     * @param  list<array{descricao:string, quantidade:float|int, valor_unitario:float, codigo_servico_lc116?:?string, cnae?:?string, nbs?:?string, aliq_iss?:?float}>  $itens
     */
    public function emitir(Empresa $empresa, Unidade $unidade, Hospedagem $hospedagem, DocumentoFiscal $documento, array $itens): DocumentoFiscal
    {
        if ($itens === []) {
            throw new NegocioException('Não há serviços/diárias para emitir NFS-e nesta hospedagem.');
        }

        if (! $empresa->nfse_nacional_habilitado && ($empresa->nfse_provider ?: 'nacional_gov') === 'nacional_gov') {
            throw new NegocioException('Habilite a NFS-e Nacional em Dados da empresa antes de emitir.');
        }

        $cMun = preg_replace('/\D+/', '', (string) $empresa->codigo_municipio_ibge);
        if (strlen((string) $cMun) !== 7) {
            throw new NegocioException('Informe o código IBGE do município (7 dígitos) em Dados da empresa.');
        }

        $valor = round(collect($itens)->sum(fn ($i) => ((float) $i['quantidade']) * ((float) $i['valor_unitario'])), 2);
        $serie = (int) ($empresa->numero_serie_nfse ?: 1);
        $numero = ((int) $empresa->numero_ultima_nfse) + 1;

        $descricao = collect($itens)->map(fn ($i) => $i['descricao'])->implode('; ');
        $lc116 = (string) ($itens[0]['codigo_servico_lc116']
            ?? $empresa->codigo_servico_hospedagem_lc116
            ?? '9.01');
        $lc116Digits = preg_replace('/\D+/', '', $lc116) ?: '901';

        $xml = $this->montarDpsXml($empresa, $unidade, $hospedagem, $cMun, $serie, $numero, $valor, $descricao, $lc116Digits, $itens[0] ?? []);
        $assinado = $this->assinarDps($empresa, $xml);

        $documento->update([
            'status' => DocumentoFiscal::STATUS_PROCESSANDO,
            'serie' => $serie,
            'numero' => $numero,
            'xml' => $assinado,
            'itens' => $itens,
            'valor_total' => $valor,
        ]);

        $arquivos = $this->certificadoA1->arquivosTemporariosPem($empresa);

        try {
            $base = ((int) $empresa->ambiente_nfe === Empresa::AMBIENTE_PRODUCAO)
                ? 'https://sefin.nfse.gov.br/SefinNacional/'
                : 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/';

            $payload = [
                'dpsXmlGZipB64' => base64_encode(gzencode($assinado)),
            ];

            $response = Http::timeout(60)
                ->withOptions([
                    'cert' => $arquivos['cert'],
                    'ssl_key' => [$arquivos['key'], ''],
                    'verify' => true,
                ])
                ->acceptJson()
                ->post($base.'nfse', $payload);

            $json = $response->json() ?? [];
            $statusHttp = $response->status();

            if ($statusHttp >= 200 && $statusHttp < 300 && (isset($json['chaveAcesso']) || isset($json['idDps']))) {
                $documento->update([
                    'status' => DocumentoFiscal::STATUS_AUTORIZADO,
                    'chave' => (string) ($json['chaveAcesso'] ?? $json['chave'] ?? ''),
                    'protocolo' => (string) ($json['idDps'] ?? $json['protocolo'] ?? ''),
                    'xml_protocolado' => $assinado,
                    'autorizado_em' => now(),
                    'retorno' => $json,
                    'mensagem_erro' => null,
                ]);
                $empresa->update(['numero_ultima_nfse' => $numero]);

                return $documento->fresh();
            }

            $motivo = (string) ($json['mensagem'] ?? $json['message'] ?? $json['erros'][0]['mensagem'] ?? $response->body());
            $documento->update([
                'status' => DocumentoFiscal::STATUS_REJEITADO,
                'mensagem_erro' => "HTTP {$statusHttp}: ".substr($motivo, 0, 500),
                'retorno' => $json ?: ['body' => $response->body()],
            ]);

            throw new IntegrationException($documento->mensagem_erro, servico: 'nfse', respostaBruta: $json);
        } catch (IntegrationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $documento->update([
                'status' => DocumentoFiscal::STATUS_ERRO,
                'mensagem_erro' => $e->getMessage(),
            ]);
            throw new IntegrationException('Falha na transmissão da NFS-e: '.$e->getMessage(), servico: 'nfse');
        } finally {
            ($arquivos['limpar'])();
        }
    }

    /**
     * @param  array<string, mixed>  $itemRef
     */
    protected function montarDpsXml(
        Empresa $empresa,
        Unidade $unidade,
        Hospedagem $hospedagem,
        string $cMun,
        int $serie,
        int $numero,
        float $valor,
        string $descricao,
        string $cTribNac,
        array $itemRef,
    ): string {
        $tpAmb = (int) ($empresa->ambiente_nfe ?: 2);
        $cnpj = preg_replace('/\D+/', '', (string) ($unidade->cnpj ?: $empresa->cnpj));
        $im = preg_replace('/\D+/', '', (string) $empresa->im);
        $dhEmi = now()->format('Y-m-d\TH:i:sP');
        $dCompet = now()->format('Y-m-d');
        $idDps = 'DPS'.str_pad($cnpj, 14, '0', STR_PAD_LEFT).str_pad((string) $serie, 5, '0', STR_PAD_LEFT).str_pad((string) $numero, 15, '0', STR_PAD_LEFT);
        $opSimpNac = match ($empresa->regime_tributario) {
            Empresa::REGIME_SIMPLES, Empresa::REGIME_SIMPLES_EXCESSO, Empresa::REGIME_MEI => '1',
            default => '3',
        };
        $vServ = number_format($valor, 2, '.', '');
        $xDesc = htmlspecialchars(substr($descricao !== '' ? $descricao : 'Servicos de hospedagem', 0, 2000), ENT_XML1);
        $cNbs = preg_replace('/\D+/', '', (string) ($itemRef['nbs'] ?? '')) ?: null;
            $aliq = number_format((float) ($itemRef['aliq_iss'] ?? 0), 2, '.', '');

        $cliente = $hospedagem->cliente;
        $cpf = preg_replace('/\D+/', '', (string) ($cliente?->cpf ?? ''));
        $toma = '';
        if (strlen((string) $cpf) === 11) {
            $toma = '<toma>'
                .'<CPF>'.$cpf.'</CPF>'
                .'<xNome>'.htmlspecialchars(substr((string) $cliente->nome, 0, 150), ENT_XML1).'</xNome>'
                .'</toma>';
        }

        $nbsTag = $cNbs ? '<cNBS>'.$cNbs.'</cNBS>' : '';
        $imTag = $im ? '<IM>'.$im.'</IM>' : '';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<DPS xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.00">'
            .'<infDPS Id="'.$idDps.'">'
            .'<tpAmb>'.$tpAmb.'</tpAmb>'
            .'<dhEmi>'.$dhEmi.'</dhEmi>'
            .'<verAplic>ParqueAquaticoSaaS1.0</verAplic>'
            .'<serie>'.$serie.'</serie>'
            .'<nDPS>'.$numero.'</nDPS>'
            .'<dCompet>'.$dCompet.'</dCompet>'
            .'<tpEmit>1</tpEmit>'
            .'<cLocEmi>'.$cMun.'</cLocEmi>'
            .'<prest>'
            .'<CNPJ>'.$cnpj.'</CNPJ>'
            .$imTag
            .'<regTrib>'
            .'<opSimpNac>'.$opSimpNac.'</opSimpNac>'
            .'<regEspTrib>0</regEspTrib>'
            .'</regTrib>'
            .'</prest>'
            .$toma
            .'<serv>'
            .'<locPrest><cLocPrestacao>'.$cMun.'</cLocPrestacao></locPrest>'
            .'<cServ>'
            .'<cTribNac>'.$cTribNac.'</cTribNac>'
            .'<xDescServ>'.$xDesc.'</xDescServ>'
            .$nbsTag
            .'</cServ>'
            .'</serv>'
            .'<valores>'
            .'<vServPrest><vServ>'.$vServ.'</vServ></vServPrest>'
            .'<trib>'
            .'<tribMun>'
            .'<tribISSQN>1</tribISSQN>'
            .'<tpRetISSQN>1</tpRetISSQN>'
            .'<pAliq>'.$aliq.'</pAliq>'
            .'</tribMun>'
            .'</trib>'
            .'</valores>'
            .'</infDPS>'
            .'</DPS>';
    }

    protected function assinarDps(Empresa $empresa, string $xml): string
    {
        $cert = $this->certificadoA1->carregar($empresa);

        try {
            return Signer::sign($cert['certificate'], $xml, 'infDPS', 'Id');
        } catch (\Throwable $e) {
            throw new NegocioException('Falha ao assinar a DPS da NFS-e: '.$e->getMessage());
        }
    }
}
