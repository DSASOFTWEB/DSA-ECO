<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\Unidade;
use App\Services\Fiscal\CertificadoA1Service;
use App\Services\Fiscal\NfseMunicipal\MunicipioNfseCatalog;
use App\Services\Fiscal\NfseMunicipal\NfseMunicipalSchemaValidator;
use App\Services\Fiscal\NfseMunicipal\Xml\AbrasfV2RpsBuilder;
use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;

function makePfx(string $senha): string
{
    $chave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'Teste'], $chave, ['digest_alg' => 'sha256']);
    $cert = openssl_csr_sign($csr, null, $chave, 365, ['digest_alg' => 'sha256']);
    openssl_pkcs12_export($cert, $pfx, $chave, $senha);

    return $pfx;
}

$senha = 'teste';
$pfx = makePfx($senha);
$certificate = Certificate::readPfx($pfx, $senha);

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

$mun = app(MunicipioNfseCatalog::class)->resolve('2704302');
$b = app(AbrasfV2RpsBuilder::class);
$r = $b->montarRps($empresa, $unidade, $h, $mun, [
    ['descricao' => 'Hospedagem', 'quantidade' => 1, 'valor_unitario' => 100],
], 1, 1);

$rpsAssinado = Signer::sign($certificate, $r['rps'], 'InfDeclaracaoPrestacaoServico', 'Id');
echo "RPS signed OK\n";
$rpsInner = $b->extrairRpsParaLote($rpsAssinado);
$lote = $b->montarLoteEnvio($empresa, $unidade, $mun, '1', $rpsInner);
$loteAssinado = Signer::sign($certificate, $lote, 'LoteRps', 'Id');

file_put_contents(storage_path('app/lote_giss_assinado.xml'), $loteAssinado);
echo "Saved storage/app/lote_giss_assinado.xml\n";

// Show Signature parent
if (preg_match('/<Signature[\s>]/', $loteAssinado)) {
    echo "Has Signature\n";
}
if (preg_match('/<\/LoteRps>\s*<Signature/s', $loteAssinado)) {
    echo "Signature AFTER LoteRps (correct for envio XSD)\n";
} elseif (preg_match('/<Signature[\s>].*<\/LoteRps>/s', $loteAssinado)) {
    echo "Signature INSIDE LoteRps (WRONG for tcLoteRps)\n";
}

try {
    app(NfseMunicipalSchemaValidator::class)->validar($loteAssinado, 'enviar-lote-rps-envio-v2_04.xsd');
    echo "OK schema assinados\n";
} catch (Throwable $e) {
    echo 'FAIL schema: '.$e->getMessage()."\n";
}
