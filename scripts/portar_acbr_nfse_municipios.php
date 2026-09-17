<?php

/**
 * Porta ACBrNFSeXServicos.ini para resources/fiscal/nfse_municipios.json.
 *
 * Uso:
 *   php scripts/portar_acbr_nfse_municipios.php [caminho-ini]
 */

$iniPath = $argv[1] ?? 'C:/Componentes/ACBr/Fontes/ACBrDFe/ACBrNFSeX/ACBrNFSeXServicos.ini';
$outPath = dirname(__DIR__).'/resources/fiscal/nfse_municipios.json';

if (! is_file($iniPath)) {
    fwrite(STDERR, "INI não encontrado: {$iniPath}\n");
    exit(1);
}

$lines = file($iniPath, FILE_IGNORE_NEW_LINES);
$sections = [];
$current = null;

foreach ($lines as $line) {
    if (! mb_check_encoding($line, 'UTF-8')) {
        $line = mb_convert_encoding($line, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
    }
    $line = trim($line);
    if ($line === '' || str_starts_with($line, ';')) {
        continue;
    }
    if (preg_match('/^\[(.+)\]$/', $line, $m)) {
        $current = $m[1];
        $sections[$current] = [];
        continue;
    }
    if ($current === null) {
        continue;
    }
    $pos = strpos($line, '=');
    if ($pos === false) {
        continue;
    }
    $key = substr($line, 0, $pos);
    $value = substr($line, $pos + 1);
    if (! mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
    }
    // Remove caracteres inválidos remanescentes.
    $value = iconv('UTF-8', 'UTF-8//IGNORE', $value) ?: $value;
    $sections[$current][$key] = $value;
}

$providers = [];
$municipios = [];

foreach ($sections as $name => $data) {
    if (preg_match('/^[0-9]{7}$/', $name)) {
        $provedor = trim((string) ($data['Provedor'] ?? ''));
        $params = array_values(array_filter(array_map(
            static fn (string $p): string => rtrim(trim($p), ':'),
            explode(';', (string) ($data['Params'] ?? ''))
        )));
        $municipios[$name] = [
            'nome' => (string) ($data['Nome'] ?? ''),
            'uf' => (string) ($data['UF'] ?? ''),
            'provedor' => $provedor,
            'versao' => (string) ($data['Versao'] ?? ''),
            'params' => $params,
            'url_producao' => (string) ($data['ProRecepcionar'] ?? ''),
            'url_homologacao' => (string) ($data['HomRecepcionar'] ?? ''),
        ];
        continue;
    }

    $providers[$name] = [
        'url_homologacao' => (string) ($data['HomRecepcionar'] ?? ''),
        'url_producao' => (string) ($data['ProRecepcionar'] ?? ''),
    ];
}

foreach ($municipios as $ibge => &$m) {
    $p = $m['provedor'];
    if ($p === '' || ! isset($providers[$p])) {
        continue;
    }
    if ($m['url_homologacao'] === '') {
        $m['url_homologacao'] = $providers[$p]['url_homologacao'];
    }
    if ($m['url_producao'] === '') {
        $m['url_producao'] = $providers[$p]['url_producao'];
    }
}
unset($m);

$out = [
    'fonte' => 'ACBrNFSeXServicos.ini',
    'gerado_em' => date('c'),
    'provedores' => $providers,
    'municipios' => $municipios,
];

if (! is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0775, true);
}

$json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, 'json_encode falhou: '.json_last_error_msg()."\n");
    exit(1);
}

if (! is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0775, true);
}

$bytes = file_put_contents($outPath, $json."\n");
if ($bytes === false) {
    fwrite(STDERR, "Falha ao gravar {$outPath}\n");
    exit(1);
}

echo 'OK municipios='.count($municipios).' provedores='.count($providers).' bytes='.$bytes.PHP_EOL;
echo 'Maceio='.json_encode($municipios['2704302'] ?? null, JSON_UNESCAPED_SLASHES).PHP_EOL;
