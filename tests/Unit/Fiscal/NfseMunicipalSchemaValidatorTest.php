<?php

namespace Tests\Unit\Fiscal;

use App\Services\Fiscal\NfseMunicipal\NfseMunicipalSchemaValidator;
use Tests\TestCase;

class NfseMunicipalSchemaValidatorTest extends TestCase
{
    public function test_caminho_padrao_aponta_para_storage_schemas_xsd_giss(): void
    {
        $validator = app(NfseMunicipalSchemaValidator::class);

        $this->assertSame(
            storage_path('SchemasXSDgiss'),
            $validator->caminhoBase()
        );
        $this->assertFileExists($validator->caminho('enviar-lote-rps-envio-v2_04.xsd'));
        $this->assertFileExists($validator->caminho('tipos-v2_04.xsd'));
        $this->assertFileExists($validator->caminho('cabecalho-v2_04.xsd'));
    }

    public function test_valida_cabecalho_giss(): void
    {
        $xml = '<cabecalho xmlns="http://www.giss.com.br/cabecalho-v2_04.xsd" versao="2.04">'
            .'<versaoDados>2.04</versaoDados>'
            .'</cabecalho>';

        app(NfseMunicipalSchemaValidator::class)->validar($xml, 'cabecalho-v2_04.xsd');

        $this->assertTrue(true);
    }
}
