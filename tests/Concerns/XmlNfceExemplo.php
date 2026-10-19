<?php

namespace Tests\Concerns;

/**
 * XML de NFC-e autorizada (modelo 65) no formato que a SEFAZ devolve
 * (nfeProc com infNFeSupl e protNFe), para testar o DANFE sem SEFAZ.
 */
trait XmlNfceExemplo
{
    protected function xmlNfceAutorizada(int $numero = 901, string $tpAmb = '2'): string
    {
        $chave = '35261012345678000199650010000009011234567890';

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
  <NFe>
    <infNFe Id="NFe{$chave}" versao="4.00">
      <ide><nNF>{$numero}</nNF><serie>1</serie><dhEmi>2026-10-19T14:30:00-03:00</dhEmi><tpAmb>{$tpAmb}</tpAmb><mod>65</mod></ide>
      <emit>
        <CNPJ>12345678000199</CNPJ><xNome>CATOLE ECO CLUBE LTDA</xNome><xFant>Catolé Aqua Park</xFant><IE>123456789</IE>
        <enderEmit><xLgr>RUA DAS AGUAS</xLgr><nro>100</nro><xBairro>CENTRO</xBairro><xMun>CAMPINA GRANDE</xMun><UF>PB</UF></enderEmit>
      </emit>
      <dest><CPF>52998224725</CPF><xNome>MARIA DA SILVA</xNome></dest>
      <det nItem="1"><prod><cProd>REF01</cProd><xProd>REFRIGERANTE LATA</xProd><uCom>UN</uCom><qCom>2.0000</qCom><vUnCom>8.00</vUnCom><vProd>16.00</vProd><vDesc>1.00</vDesc></prod></det>
      <det nItem="2"><prod><cProd>AGU01</cProd><xProd>AGUA MINERAL</xProd><uCom>UN</uCom><qCom>1.0000</qCom><vUnCom>4.00</vUnCom><vProd>4.00</vProd></prod></det>
      <total><ICMSTot><vProd>20.00</vProd><vDesc>1.00</vDesc><vNF>19.00</vNF></ICMSTot></total>
      <pag><detPag><tPag>17</tPag><vPag>19.00</vPag></detPag></pag>
      <infAdic><infCpl>Obrigado pela preferência!</infCpl></infAdic>
    </infNFe>
    <infNFeSupl>
      <qrCode>https://www.sefaz.pb.gov.br/nfce?p={$chave}|2|2|1|ABCDEF123456</qrCode>
      <urlChave>www.sefaz.pb.gov.br/nfce/consulta</urlChave>
    </infNFeSupl>
  </NFe>
  <protNFe versao="4.00"><infProt><chNFe>{$chave}</chNFe><dhRecbto>2026-10-19T14:30:05-03:00</dhRecbto><nProt>325260000123456</nProt><cStat>100</cStat></infProt></protNFe>
</nfeProc>
XML;
    }
}
