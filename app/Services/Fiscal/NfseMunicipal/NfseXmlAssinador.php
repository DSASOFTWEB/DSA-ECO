<?php

namespace App\Services\Fiscal\NfseMunicipal;

use App\Exceptions\NegocioException;
use DOMDocument;
use DOMElement;
use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;

/**
 * Assinatura XMLDSig para GISS: o Signer do NFePHP ignora nova assinatura
 * se já existir qualquer <Signature> (ex.: RPS dentro do lote). Aqui forçamos
 * assinar a tag pedida mesmo com assinaturas prévias.
 *
 * Importante: NÃO clonar o nó antes do C14N — em PHP o clone perde o
 * namespace herdado e o digest vira o SHA-1 de string vazia (E172 no GISS).
 */
class NfseXmlAssinador
{
    private const NS_DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    /**
     * @param  array{algorithm?:int,canonical?:array,rootname?:string}  $opcoes
     */
    public function assinar(
        Certificate $certificate,
        string $xml,
        string $tagName,
        string $mark = 'Id',
        array $opcoes = [],
    ): string {
        $xml = $this->garantirXmlUtf8($xml);
        $algorithm = $opcoes['algorithm'] ?? OPENSSL_ALGO_SHA1;
        $canonical = $opcoes['canonical'] ?? Signer::CANONICAL;
        $rootname = (string) ($opcoes['rootname'] ?? '');

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        if (! @$dom->loadXML($xml)) {
            throw new NegocioException('XML inválido para assinatura NFS-e municipal.');
        }

        $root = $dom->documentElement;
        if ($rootname !== '') {
            $root = $dom->getElementsByTagName($rootname)->item(0) ?: $root;
        }
        /** @var DOMElement|null $node */
        $node = $dom->getElementsByTagName($tagName)->item(0);
        if (! $node instanceof DOMElement || $root === null) {
            throw new NegocioException("Tag {$tagName} não encontrada para assinar a NFS-e municipal.");
        }

        if (! $node->hasAttribute($mark) || trim($node->getAttribute($mark)) === '') {
            throw new NegocioException("Atributo {$mark} ausente em {$tagName} para assinatura.");
        }

        // URI "#Id" exige atributo tipado como ID no DOM.
        $node->setIdAttribute($mark, true);

        // Remove Signature filha do root cujo Reference aponta para este Id.
        $id = '#'.$node->getAttribute($mark);
        foreach (iterator_to_array($root->childNodes) as $child) {
            if (! $child instanceof DOMElement || $child->localName !== 'Signature') {
                continue;
            }
            $ref = $child->getElementsByTagName('Reference')->item(0);
            if ($ref && $ref->getAttribute('URI') === $id) {
                $root->removeChild($child);
            }
        }

        $this->criarAssinatura($certificate, $dom, $root, $node, $mark, $algorithm, $canonical);

        $out = $dom->saveXML($dom->documentElement, LIBXML_NOXMLDECL) ?: '';

        return $this->garantirXmlUtf8($out);
    }

    /**
     * @param  array<int, mixed>  $canonical
     */
    protected function criarAssinatura(
        Certificate $certificate,
        DOMDocument $dom,
        \DOMNode $root,
        DOMElement $node,
        string $mark,
        int $algorithm,
        array $canonical,
    ): void {
        $nsCannonMethod = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
        $nsSignatureMethod = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';
        $nsDigestMethod = 'http://www.w3.org/2000/09/xmldsig#sha1';
        $digestAlgorithm = 'sha1';
        if ($algorithm === OPENSSL_ALGO_SHA256) {
            $digestAlgorithm = 'sha256';
            $nsSignatureMethod = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
            $nsDigestMethod = 'http://www.w3.org/2001/04/xmlenc#sha256';
        }

        $idSigned = trim($node->getAttribute($mark));
        // Digest no próprio nó (não clonar): herda xmlns do ancestral.
        $c14n = $node->C14N($canonical[0], $canonical[1], $canonical[2] ?? null, $canonical[3] ?? null);
        $digestValue = base64_encode(hash($digestAlgorithm, $c14n, true));

        // Prefixo ds: como nos exemplos oficiais GISS / XSD dsig:Signature.
        $signatureNode = $dom->createElementNS(self::NS_DSIG, 'ds:Signature');
        $root->appendChild($signatureNode);

        $signedInfoNode = $dom->createElementNS(self::NS_DSIG, 'ds:SignedInfo');
        $signatureNode->appendChild($signedInfoNode);

        $canonicalMethodNode = $dom->createElementNS(self::NS_DSIG, 'ds:CanonicalizationMethod');
        $signedInfoNode->appendChild($canonicalMethodNode);
        $canonicalMethodNode->setAttribute('Algorithm', $nsCannonMethod);

        $signatureMethodNode = $dom->createElementNS(self::NS_DSIG, 'ds:SignatureMethod');
        $signedInfoNode->appendChild($signatureMethodNode);
        $signatureMethodNode->setAttribute('Algorithm', $nsSignatureMethod);

        $referenceNode = $dom->createElementNS(self::NS_DSIG, 'ds:Reference');
        $signedInfoNode->appendChild($referenceNode);
        $referenceNode->setAttribute('URI', $idSigned !== '' ? '#'.$idSigned : '');

        $transformsNode = $dom->createElementNS(self::NS_DSIG, 'ds:Transforms');
        $referenceNode->appendChild($transformsNode);

        $transf1 = $dom->createElementNS(self::NS_DSIG, 'ds:Transform');
        $transformsNode->appendChild($transf1);
        $transf1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');

        $transf2 = $dom->createElementNS(self::NS_DSIG, 'ds:Transform');
        $transformsNode->appendChild($transf2);
        $transf2->setAttribute('Algorithm', $nsCannonMethod);

        $digestMethodNode = $dom->createElementNS(self::NS_DSIG, 'ds:DigestMethod');
        $referenceNode->appendChild($digestMethodNode);
        $digestMethodNode->setAttribute('Algorithm', $nsDigestMethod);

        $digestValueNode = $dom->createElementNS(self::NS_DSIG, 'ds:DigestValue', $digestValue);
        $referenceNode->appendChild($digestValueNode);

        $c14nSignedInfo = $signedInfoNode->C14N($canonical[0], $canonical[1], $canonical[2] ?? null, $canonical[3] ?? null);
        $signatureValue = base64_encode($certificate->sign($c14nSignedInfo, $algorithm));

        $signatureValueNode = $dom->createElementNS(self::NS_DSIG, 'ds:SignatureValue', $signatureValue);
        $signatureNode->appendChild($signatureValueNode);

        $keyInfoNode = $dom->createElementNS(self::NS_DSIG, 'ds:KeyInfo');
        $signatureNode->appendChild($keyInfoNode);
        $x509DataNode = $dom->createElementNS(self::NS_DSIG, 'ds:X509Data');
        $keyInfoNode->appendChild($x509DataNode);
        $x509CertificateNode = $dom->createElementNS(
            self::NS_DSIG,
            'ds:X509Certificate',
            $certificate->publicKey->unFormated()
        );
        $x509DataNode->appendChild($x509CertificateNode);
    }

    protected function garantirXmlUtf8(string $xml): string
    {
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;
        $trimmed = ltrim($xml);
        $decl = '<'.'?xml version="1.0" encoding="UTF-8"?'.'>';
        if (! str_starts_with($trimmed, '<'.'?xml')) {
            return $decl.$trimmed;
        }

        return preg_replace('/^<\?xml[^?]*\?>/i', $decl, $trimmed, 1) ?? $trimmed;
    }
}
