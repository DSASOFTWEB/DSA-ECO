<?php

$xml = file_get_contents(__DIR__.'/../storage/app/lote_giss_assinado.xml');
if ($xml === false) {
    // try inside container path via argv
    $xml = file_get_contents($argv[1] ?? 'storage/app/lote_giss_assinado.xml');
}
$dom = new DOMDocument();
$dom->loadXML($xml);
function walk($n, $depth = 0)
{
    if ($n->nodeType === XML_ELEMENT_NODE) {
        $extra = '';
        if ($n->hasAttribute('Id')) {
            $extra = ' Id='.$n->getAttribute('Id');
        }
        echo str_repeat('  ', $depth).$n->nodeName.$extra.PHP_EOL;
        foreach ($n->childNodes as $c) {
            walk($c, $depth + 1);
        }
    }
}
walk($dom->documentElement);
