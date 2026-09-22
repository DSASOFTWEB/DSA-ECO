<?php

namespace App\Services\Fiscal\NfseMunicipal\Soap;

use App\Exceptions\IntegrationException;
use Illuminate\Support\Facades\Http;

/**
 * Cliente SOAP 1.1 ABRASF (nfseCabecMsg + nfseDadosMsg) com mTLS e/ou Basic Auth.
 */
class AbrasfSoapClient
{
    /**
     * @param  array{cert?:string,key?:string}|null  $pem  PEM para mTLS (certificado A1)
     * @param  array{user?:string,password?:string}|null  $basicAuth  Usuário/senha do portal
     */
    public function chamar(
        string $url,
        string $soapAction,
        string $requestElement,
        string $cabecalho,
        string $dadosMsg,
        ?array $pem = null,
        ?array $basicAuth = null,
    ): string {
        // GISS valida o XML interno: declaração XML dentro de nfseDadosMsg gera E160.
        $cabecEsc = htmlspecialchars($this->semDeclaracaoXml($cabecalho), ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $dadosEsc = htmlspecialchars($this->semDeclaracaoXml($dadosMsg), ENT_XML1 | ENT_COMPAT, 'UTF-8');

        $decl = '<'.'?xml version="1.0" encoding="UTF-8"?'.'>';
        $body = $decl
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

        $pending = Http::timeout(90)
            ->withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => $soapAction,
            ]);

        $options = ['verify' => true];
        if (is_array($pem) && ! empty($pem['cert']) && ! empty($pem['key'])) {
            $options['cert'] = $pem['cert'];
            $options['ssl_key'] = [$pem['key'], ''];
        }
        $pending = $pending->withOptions($options);

        $user = trim((string) ($basicAuth['user'] ?? ''));
        $pass = (string) ($basicAuth['password'] ?? '');
        if ($user !== '') {
            $pending = $pending->withBasicAuth($user, $pass);
        }

        $response = $pending->withBody($body, 'text/xml; charset=utf-8')->post($url);

        $xml = $response->body();
        if (! $response->successful() && trim($xml) === '') {
            throw new IntegrationException(
                'Falha SOAP NFS-e municipal HTTP '.$response->status(),
                servico: 'nfse-municipal',
            );
        }

        return $this->extrairOutputXml($xml);
    }

    protected function semDeclaracaoXml(string $xml): string
    {
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;
        $xml = preg_replace('/^<\?xml[^?]*\?>\s*/i', '', ltrim($xml)) ?? ltrim($xml);

        return $xml;
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

        foreach (['EnviarLoteRpsResposta', 'ConsultarLoteRpsResposta'] as $tag) {
            if (preg_match('/<'.$tag.'\b[^>]*>.*<\/'.$tag.'>/is', $soapXml, $m)) {
                return $m[0];
            }
        }

        return $soapXml;
    }
}
