<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoFiscal extends Model
{
    use BelongsToTenant;

    public const MODELO_NFCE = 'nfce';

    public const MODELO_NFE = 'nfe';

    public const MODELO_NFSE = 'nfse';

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_PROCESSANDO = 'processando';

    public const STATUS_AUTORIZADO = 'autorizado';

    public const STATUS_REJEITADO = 'rejeitado';

    public const STATUS_CANCELADO = 'cancelado';

    public const STATUS_ERRO = 'erro';

    protected $table = 'documentos_fiscais';

    protected $fillable = [
        'empresa_id', 'unidade_id', 'hospedagem_id', 'cliente_id', 'emitido_por_id',
        'modelo', 'origem', 'ambiente', 'status', 'serie', 'numero', 'chave', 'protocolo', 'recibo',
        'valor_total', 'forma_pagamento', 'xml', 'xml_protocolado', 'xml_path', 'xml_envio_path', 'link',
        'itens', 'retorno', 'mensagem_erro', 'autorizado_em',
    ];

    protected $casts = [
        'ambiente' => 'integer',
        'serie' => 'integer',
        'numero' => 'integer',
        'valor_total' => 'decimal:2',
        'itens' => 'array',
        'retorno' => 'array',
        'autorizado_em' => 'datetime',
    ];

    public function hospedagem(): BelongsTo
    {
        return $this->belongsTo(Hospedagem::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por_id');
    }

    public function estaAutorizado(): bool
    {
        return $this->status === self::STATUS_AUTORIZADO;
    }

    public function temXmlArquivo(): bool
    {
        return filled($this->xml_path) || filled($this->xml_protocolado) || filled($this->xml);
    }
}
