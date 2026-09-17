<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\Unidade;
use App\Services\Fiscal\NfseMunicipal\MunicipioNfseCatalog;
use App\Services\Fiscal\NfseMunicipal\NfseMunicipalSchemaValidator;
use App\Services\Fiscal\NfseMunicipal\Xml\AbrasfV2RpsBuilder;

$empresa = new Empresa([
    'cnpj' => '22195708400012',
    'im' => '12345',
    'regime_tributario' => 'simples',
    'codigo_municipio_ibge' => '2704302',
    'codigo_servico_hospedagem_lc116' => '09.01.05',
    'codigo_tributacao_municipal_hospedagem' => '010',
    'aliquota_iss_hospedagem' => 5,
]);
$unidade = new Unidade(['cnpj' => '22195708400012', 'uf' => 'AL']);
$cliente = new Cliente([
    'cpf' => '52998224725',
    'nome' => 'Teste',
    'endereco' => 'Rua A',
    'numero' => '10',
    'bairro' => 'Centro',
    'cep' => '57020000',
    'uf' => 'AL',
]);
$h = new Hospedagem([]);
$h->setRelation('cliente', $cliente);
$h->setRelation('unidade', $unidade);
$h->empresa = $empresa;

$mun = app(MunicipioNfseCatalog::class)->resolve('2704302');
$b = app(AbrasfV2RpsBuilder::class);
$r = $b->montarRps($empresa, $unidade, $h, $mun, [
    ['descricao' => 'Hospedagem', 'quantidade' => 1, 'valor_unitario' => 100],
], 1, 1);
$lote = $b->montarLoteEnvio($empresa, $unidade, $mun, '1', $r['rps']);

echo $lote.PHP_EOL.'---'.PHP_EOL;

try {
    app(NfseMunicipalSchemaValidator::class)->validar($lote, 'enviar-lote-rps-envio-v2_04.xsd');
    echo "OK schema\n";
} catch (Throwable $e) {
    echo 'FAIL: '.$e->getMessage().PHP_EOL;
}
