<?php

namespace App\Services\Fiscal\NfseMunicipal;

/**
 * Espelho de TNFSeResultadoEmissao (Unique.DFe.NFSe.Emissor).
 */
class NfseMunicipalResultado
{
    public function __construct(
        public bool $sucesso = false,
        public string $numero = '',
        public string $serie = '',
        public string $codigoVerificacao = '',
        public string $protocolo = '',
        public string $xmlEnvio = '',
        public string $xmlAutorizado = '',
        public bool $emProcessamento = false,
        public string $codigoErro = '',
        public string $mensagemErro = '',
        public string $numeroLote = '',
        /** @var array<string, mixed> */
        public array $retornoBruto = [],
    ) {}

    public static function erro(string $mensagem, string $codigo = '', string $protocolo = ''): self
    {
        return new self(
            sucesso: false,
            protocolo: $protocolo,
            codigoErro: $codigo,
            mensagemErro: $mensagem,
        );
    }

    public static function processando(string $protocolo, string $numeroLote = '', string $xmlEnvio = ''): self
    {
        return new self(
            sucesso: false,
            protocolo: $protocolo,
            xmlEnvio: $xmlEnvio,
            emProcessamento: true,
            mensagemErro: 'Lote em processamento na prefeitura. Protocolo: '.$protocolo.'. Consulte novamente em instantes.',
            numeroLote: $numeroLote,
        );
    }
}
