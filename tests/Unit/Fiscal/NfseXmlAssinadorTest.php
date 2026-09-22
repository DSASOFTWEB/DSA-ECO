<?php

namespace Tests\Unit\Fiscal;

use App\Services\Fiscal\NfseMunicipal\NfseXmlAssinador;
use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NfseXmlAssinadorTest extends TestCase
{
    #[Test]
    public function assina_inf_declaracao_com_digest_valido(): void
    {
        $certificate = $this->certificadoFake();
        $xml = $this->rpsXml();

        $assinado = app(NfseXmlAssinador::class)->assinar(
            $certificate,
            $xml,
            'InfDeclaracaoPrestacaoServico',
            'Id',
            ['rootname' => 'Rps']
        );

        $this->assertStringContainsString('ds:Signature', $assinado);
        $this->assertTrue(Signer::isSigned($assinado, 'InfDeclaracaoPrestacaoServico'));
    }

    #[Test]
    public function assina_lote_mesmo_com_assinatura_do_rps(): void
    {
        $certificate = $this->certificadoFake();
        $rps = app(NfseXmlAssinador::class)->assinar(
            $certificate,
            $this->rpsXml(),
            'InfDeclaracaoPrestacaoServico',
            'Id',
            ['rootname' => 'Rps']
        );

        $lote = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<EnviarLoteRpsEnvio xmlns="http://www.giss.com.br/enviar-lote-rps-envio-v2_04.xsd">'
            .'<LoteRps Id="Lote_1" versao="2.04">'
            .'<NumeroLote xmlns="http://www.giss.com.br/tipos-v2_04.xsd">1</NumeroLote>'
            .'<Prestador xmlns="http://www.giss.com.br/tipos-v2_04.xsd"><CpfCnpj><Cnpj>12345678000199</Cnpj></CpfCnpj></Prestador>'
            .'<QuantidadeRps xmlns="http://www.giss.com.br/tipos-v2_04.xsd">1</QuantidadeRps>'
            .'<ListaRps xmlns="http://www.giss.com.br/tipos-v2_04.xsd">'.$this->semDeclaracao($rps).'</ListaRps>'
            .'</LoteRps>'
            .'</EnviarLoteRpsEnvio>';

        $loteAssinado = app(NfseXmlAssinador::class)->assinar(
            $certificate,
            $lote,
            'LoteRps',
            'Id',
            ['rootname' => 'EnviarLoteRpsEnvio']
        );

        $this->assertSame(2, preg_match_all('/<ds:Signature\b/', $loteAssinado));
        // RPS isolado continua íntegro; no lote o NFePHP::isSigned pega a 1ª Signature.
        $this->assertTrue(Signer::isSigned($rps, 'InfDeclaracaoPrestacaoServico'));
        $this->assertTrue($this->digestDaReferenciaConfere($loteAssinado, 'Lote_1', 'LoteRps'));
        $this->assertTrue($this->digestDaReferenciaConfere($loteAssinado, 'Dec_11', 'InfDeclaracaoPrestacaoServico'));
    }

    private function digestDaReferenciaConfere(string $xml, string $id, string $tagName): bool
    {
        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        $node = $dom->getElementsByTagName($tagName)->item(0);
        $this->assertNotNull($node);

        $uri = '#'.$id;
        $digestInformado = null;
        foreach ($dom->getElementsByTagName('Reference') as $ref) {
            if ($ref->getAttribute('URI') !== $uri) {
                continue;
            }
            $digestInformado = $ref->getElementsByTagName('DigestValue')->item(0)?->nodeValue;
            break;
        }
        $this->assertNotEmpty($digestInformado);

        $calc = base64_encode(hash('sha1', $node->C14N(true, false), true));

        return hash_equals((string) $digestInformado, $calc);
    }

    private function rpsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Rps xmlns="http://www.giss.com.br/tipos-v2_04.xsd">'
            .'<InfDeclaracaoPrestacaoServico Id="Dec_11">'
            .'<Rps><IdentificacaoRps><Numero>1</Numero><Serie>1</Serie><Tipo>1</Tipo></IdentificacaoRps>'
            .'<DataEmissao>2026-09-21</DataEmissao><Status>1</Status></Rps>'
            .'<Competencia>2026-09-21</Competencia>'
            .'<Servico><Valores><ValorServicos>10.00</ValorServicos>'
            .'<trib><totTrib><indTotTrib>0</indTotTrib></totTrib></trib></Valores>'
            .'<IssRetido>2</IssRetido><ItemListaServico>09.01</ItemListaServico>'
            .'<Discriminacao>Teste</Discriminacao><CodigoMunicipio>2704302</CodigoMunicipio>'
            .'<ExigibilidadeISS>1</ExigibilidadeISS><MunicipioIncidencia>2704302</MunicipioIncidencia></Servico>'
            .'<Prestador><CpfCnpj><Cnpj>12345678000199</Cnpj></CpfCnpj></Prestador>'
            .'<OptanteSimplesNacional>1</OptanteSimplesNacional><IncentivoFiscal>2</IncentivoFiscal>'
            .'</InfDeclaracaoPrestacaoServico></Rps>';
    }

    private function certificadoFake(): Certificate
    {
        $chave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'Teste'], $chave, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $chave, 365, ['digest_alg' => 'sha256']);
        openssl_pkcs12_export($cert, $pfx, $chave, '123');

        return Certificate::readPfx($pfx, '123');
    }

    private function semDeclaracao(string $xml): string
    {
        return preg_replace('/^<\?xml[^?]*\?>\s*/i', '', ltrim($xml)) ?? ltrim($xml);
    }
}
