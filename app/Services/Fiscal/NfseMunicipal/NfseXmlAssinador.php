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
 */
class NfseXmlAssinador
{
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

        // Se esta tag já tem Signature filha direta, remove para reassinar.
        foreach (iterator_to_array($root->childNodes) as $child) {
            if ($child instanceof DOMElement && $child->localName === 'Signature' && $child->parentNode === $root) {
                // Mantém assinaturas de outros nós (RPS); só evita duplicar no root atual
                // quando URI aponta para o mesmo Id — removemos Signature cujo Reference URI casa.
                $id = '#'.$node->getAttribute($mark);
                $refs = $child->getElementsByTagName('Reference');
                $ref = $refs->item(0);
                if ($ref && $ref->getAttribute('URI') === $id) {
                    $root->removeChild($child);
                }
            }
        }

        $this->criarAssinatura($certificate, $dom, $root, $node, $mark, $algorithm, $canonical);

        $out = $dom->saveXML($dom->documentElement, LIBXML_NOXMLDECL) ?: '';

        return $this->garantirXmlUtf8($out);
    }

    /**
     * Espelho de NFePHP\Common\Signer::createSignature (sempre acrescenta).
     *
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
        $nsDSIG = 'http://www.w3.org/2000/09/xmldsig#';
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
        $canonicalNode = clone $node;
        $c14n = $canonicalNode->C14N($canonical[0], $canonical[1]);
        $digestValue = base64_encode(hash($digestAlgorithm, $c14n, true));

        $signatureNode = $dom->createElementNS($nsDSIG, 'Signature');
        $root->appendChild($signatureNode);

        $signedInfoNode = $dom->createElement('SignedInfo');
        $signatureNode->appendChild($signedInfoNode);

        $canonicalMethodNode = $dom->createElement('CanonicalizationMethod');
        $signedInfoNode->appendChild($canonicalMethodNode);
        $canonicalMethodNode->setAttribute('Algorithm', $nsCannonMethod);

        $signatureMethodNode = $dom->createElement('SignatureMethod');
        $signedInfoNode->appendChild($signatureMethodNode);
        $signatureMethodNode->setAttribute('Algorithm', $nsSignatureMethod);

        $referenceNode = $dom->createElement('Reference');
        $signedInfoNode->appendChild($referenceNode);
        $referenceNode->setAttribute('URI', $idSigned !== '' ? '#'.$idSigned : '');

        $transformsNode = $dom->createElement('Transforms');
        $referenceNode->appendChild($transformsNode);
        $transf1 = $dom->createElement('Transform');
        $transformsNode->appendChild($transf1);
        $transf1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transf2 = $dom->createElement('Transform');
        $transformsNode->appendChild($transf2);
        $transf2->setAttribute('Algorithm', $nsCannonMethod);

        $digestMethodNode = $dom->createElement('DigestMethod');
        $referenceNode->appendChild($digestMethodNode);
        $digestMethodNode->setAttribute('Algorithm', $nsDigestMethod);

        $digestValueNode = $dom->createElement('DigestValue', $digestValue);
        $referenceNode->appendChild($digestValueNode);

        $c14nSignedInfo = $signedInfoNode->C14N($canonical[0], $canonical[1]);
        $signatureValue = base64_encode($certificate->sign($c14nSignedInfo, $algorithm));
        $signatureValueNode = $dom->createElement('SignatureValue', $signatureValue);
        $signatureNode->appendChild($signatureValueNode);

        $keyInfoNode = $dom->createElement('KeyInfo');
        $signatureNode->appendChild($keyInfoNode);
        $x509DataNode = $dom->createElement('X509Data');
        $keyInfoNode->appendChild($x509DataNode);
        $x509CertificateNode = $dom->createElement('X509Certificate', $certificate->publicKey->unFormated());
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
