<?php

namespace App\Services\Fiscal\NfseMunicipal;

use App\Exceptions\NegocioException;
use DOMDocument;
use LibXMLError;

/**
 * Valida XML GISS ABRASF 2.04 contra os XSD em storage/SchemasXSDgiss.
 */
class NfseMunicipalSchemaValidator
{
    public function caminhoBase(): string
    {
        $configured = (string) config('parque.nfse_giss_schemas_path', '');
        if ($configured !== '' && is_dir($configured)) {
            return rtrim($configured, DIRECTORY_SEPARATOR);
        }

        return storage_path('SchemasXSDgiss');
    }

    public function caminho(string $arquivoXsd): string
    {
        return $this->caminhoBase().DIRECTORY_SEPARATOR.ltrim($arquivoXsd, '/\\');
    }

    /**
     * @throws NegocioException
     */
    public function validar(string $xml, string $arquivoXsd): void
    {
        $xsd = $this->caminho($arquivoXsd);
        if (! is_file($xsd)) {
            throw new NegocioException(
                "Schema XSD GISS não encontrado: {$arquivoXsd}. "
                .'Coloque os arquivos em storage/SchemasXSDgiss (ex.: tipos-v2_04.xsd, enviar-lote-rps-envio-v2_04.xsd).'
            );
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;

            if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE)) {
                throw new NegocioException('XML NFS-e municipal inválido (não parseável): '.$this->formatarErros());
            }

            if (! @$dom->schemaValidate($xsd)) {
                throw new NegocioException('XML NFS-e municipal rejeitado pelo schema GISS ('.$arquivoXsd.'): '.$this->formatarErros());
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Validação opcional: se a pasta/schema não existir, apenas registra e segue
     * (útil em ambientes sem os XSD montados). Com $obrigatorio=true, falha.
     */
    public function validarSeDisponivel(string $xml, string $arquivoXsd, bool $obrigatorio = false): void
    {
        $xsd = $this->caminho($arquivoXsd);
        if (! is_file($xsd)) {
            if ($obrigatorio) {
                $this->validar($xml, $arquivoXsd);
            }

            return;
        }

        $this->validar($xml, $arquivoXsd);
    }

    protected function formatarErros(): string
    {
        /** @var list<LibXMLError> $erros */
        $erros = libxml_get_errors();
        if ($erros === []) {
            return 'erro desconhecido';
        }

        $msgs = [];
        foreach (array_slice($erros, 0, 5) as $erro) {
            $msgs[] = trim($erro->message).' (linha '.$erro->line.')';
        }

        return implode(' | ', $msgs);
    }
}
