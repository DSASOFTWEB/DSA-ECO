<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Carteirinha;
use App\Models\Cliente;
use App\Models\Dependente;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Emissão e gestão das carteirinhas digitais (QR Code) usadas na catraca
 * de entrada do parque. O "código" gravado no banco é um token opaco
 * (não é o CPF nem nenhum dado pessoal) — o QR Code apenas carrega esse
 * token, que o terminal de acesso envia para App\Services\AcessoService.
 */
class CarteirinhaService
{
    public function emitirParaCliente(Cliente $cliente): Carteirinha
    {
        if ($carteirinha = $cliente->carteirinha) {
            return $carteirinha;
        }

        return DB::transaction(fn () => Carteirinha::create([
            'cliente_id' => $cliente->id,
            'codigo' => $this->gerarCodigoUnico(),
            'status' => 'ativa',
            'emitida_em' => now(),
        ]));
    }

    public function emitirParaDependente(Dependente $dependente): Carteirinha
    {
        if ($carteirinha = $dependente->carteirinha) {
            return $carteirinha;
        }

        return DB::transaction(fn () => Carteirinha::create([
            'dependente_id' => $dependente->id,
            'codigo' => $this->gerarCodigoUnico(),
            'status' => 'ativa',
            'emitida_em' => now(),
        ]));
    }

    public function bloquear(Carteirinha $carteirinha, string $motivo): Carteirinha
    {
        $carteirinha->update(['status' => 'bloqueada', 'motivo_bloqueio' => $motivo]);

        return $carteirinha;
    }

    public function desbloquear(Carteirinha $carteirinha): Carteirinha
    {
        $carteirinha->update(['status' => 'ativa', 'motivo_bloqueio' => null]);

        return $carteirinha;
    }

    /**
     * Gera a imagem PNG do QR Code para exibição/impressão da carteirinha.
     * Retorna o caminho (disco "public") do arquivo gerado.
     */
    public function gerarImagemQrCode(Carteirinha $carteirinha): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($carteirinha->codigo)
            ->size(400)
            ->margin(10)
            ->build();

        $caminho = "carteirinhas/{$carteirinha->codigo}.png";
        Storage::disk('public')->put($caminho, $result->getString());

        return $caminho;
    }

    /**
     * QR Code já embutido como data URI (base64) — usado na impressão em
     * PDF, onde o dompdf não pode simplesmente apontar para a rota
     * carteirinhas.qrcode (precisaria de uma requisição HTTP à parte).
     */
    public function gerarQrCodeDataUri(Carteirinha $carteirinha, int $size = 240): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($carteirinha->codigo)
            ->size($size)
            ->margin(6)
            ->build();

        return 'data:image/png;base64,'.base64_encode($result->getString());
    }

    protected function gerarCodigoUnico(): string
    {
        do {
            $codigo = 'CART-'.Str::upper(Str::random(20));
        } while (Carteirinha::where('codigo', $codigo)->exists());

        return $codigo;
    }
}
