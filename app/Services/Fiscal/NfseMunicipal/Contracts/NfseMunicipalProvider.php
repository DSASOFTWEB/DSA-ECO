<?php

namespace App\Services\Fiscal\NfseMunicipal\Contracts;

use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\Unidade;
use App\Services\Fiscal\NfseMunicipal\NfseMunicipalResultado;

interface NfseMunicipalProvider
{
    /**
     * @param  array{
     *   ibge:string,nome:string,uf:string,provedor:string,versao:string,
     *   params:list<string>,url_producao:string,url_homologacao:string
     * }  $municipio
     * @param  list<array<string, mixed>>  $itens
     */
    public function emitir(
        Empresa $empresa,
        Unidade $unidade,
        Hospedagem $hospedagem,
        array $municipio,
        array $itens,
        int $serie,
        int $numeroRps,
        string $numeroLote,
    ): NfseMunicipalResultado;

    /**
     * @param  array{
     *   ibge:string,nome:string,uf:string,provedor:string,versao:string,
     *   params:list<string>,url_producao:string,url_homologacao:string
     * }  $municipio
     */
    public function consultarLote(
        Empresa $empresa,
        Unidade $unidade,
        array $municipio,
        string $protocolo,
        string $numeroLote,
    ): NfseMunicipalResultado;
}
