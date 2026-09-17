<?php

namespace App\Services\Fiscal\NfseMunicipal\Soap;

use App\Exceptions\IntegrationException;
use Illuminate\Support\Facades\Http;

/**
 * Cliente SOAP 1.1 ABRASF (nfseCabecMsg + nfseDadosMsg) com mTLS.
 */
class AbrasfSoapClient
{
    /**
     * @param  array{cert:string,key:string}  $pem
     */
    public function chamar(
        string $url,
        string $soapAction,
        string $requestElement,
        string $cabecalho,
        string $dadosMsg,
        array $pem,
    ): string {
        $cabecEsc = htmlspecialchars($cabecalho, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $dadosEsc = htmlspecialchars($dadosMsg, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        $body = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"'
            .' xmlns:nfse="http://nfse.abrasf.org.br">'
            .'<soapenv:Header/>'
            .'<soapenv:Body>'
            .'<nfse:'.$requestElement.'>'
            .'<nfseCabecMsg>'.$cabecEsc.'</nfseCabecMsg>'
            .'<nfseDadosMsg>'.$dadosEsc.'</nfseDadosMsg>'
            .'</nfse:'.$requestElement.'>'
            .'</soapenv:Body>'
            .'</soapenv:Envelope>';

        $response = Http::timeout(90)
            ->withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => $soapAction,
            ])
            ->withOptions([
                'cert' => $pem['cert'],
                'ssl_key' => [$pem['key'], ''],
                'verify' => true,
            ])
            ->withBody($body, 'text/xml; charset=utf-8')
            ->post($url);

        $xml = $response->body();
        if (! $response->successful() && trim($xml) === '') {
            throw new IntegrationException(
                'Falha SOAP NFS-e municipal HTTP '.$response->status(),
                servico: 'nfse-municipal',
            );
        }

        return $this->extrairOutputXml($xml);
    }

    protected function extrairOutputXml(string $soapXml): string
    {
        if (preg_match('/<(?:\w+:)?outputXML[^>]*>(.*?)<\/(?:\w+:)?outputXML>/is', $soapXml, $m)) {
            $inner = html_entity_decode($m[1], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $inner = trim($inner);
            if ($inner !== '') {
                return $inner;
            }
        }

        // Alguns provedores devolvem a resposta já como XML filho.
        foreach (['EnviarLoteRpsResposta', 'ConsultarLoteRpsResposta'] as $tag) {
            if (preg_match('/<'.$tag.'\b[^>]*>.*<\/'.$tag.'>/is', $soapXml, $m)) {
                return $m[0];
            }
        }

        return $soapXml;
    }
}
