<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Acesso extends Model
{
    use HasFactory;

    public $timestamps = true;

    /**
     * Origens em que o "voucher" é comprado/gerado remotamente, sem
     * ninguém da recepção presente — por isso exigem validação (escaneio
     * do QR na portaria) antes de a entrada valer de fato. Origens
     * presenciais (catraca, PDV, check-in na recepção) já têm um humano
     * conferindo na hora, então não precisam dessa segunda checagem.
     */
    public const ORIGENS_QUE_EXIGEM_VALIDACAO = ['online', 'checkin_online', 'cortesia'];

    protected $fillable = [
        'carteirinha_id', 'cliente_id', 'dependente_id', 'venda_id', 'venda_item_id', 'tipo_entrada_id', 'unidade_id', 'tipo', 'origem',
        'codigo_validacao', 'dispositivo', 'autorizado', 'motivo_negado', 'observacao', 'validade_ate', 'validado_em', 'validado_por_id',
        'registrado_por_id', 'registrado_em',
    ];

    protected $casts = [
        'autorizado' => 'boolean',
        'registrado_em' => 'datetime',
        'validade_ate' => 'date',
        'validado_em' => 'datetime',
    ];

    public function carteirinha(): BelongsTo
    {
        return $this->belongsTo(Carteirinha::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function dependente(): BelongsTo
    {
        return $this->belongsTo(Dependente::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function vendaItem(): BelongsTo
    {
        return $this->belongsTo(VendaItem::class);
    }

    public function tipoEntrada(): BelongsTo
    {
        return $this->belongsTo(TipoEntrada::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por_id');
    }

    /**
     * Acesso não tem empresa_id/BelongsToTenant (a tabela é alimentada por
     * catraca/PDV/link público, muitas vezes sem usuário autenticado, então
     * o TenantScope automático não se aplica). Todo lugar que consulta
     * Acesso diretamente por id/código — em vez de só através de uma
     * relação já filtrada — precisa aplicar este scope manualmente, senão
     * vaza registro de outras empresas.
     */
    public function scopeDaEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->whereHas('unidade', fn ($q) => $q->where('empresa_id', $empresaId));
    }

    /**
     * Nome de quem entrou, seja o titular de uma carteirinha (catraca/QR)
     * ou o cliente identificado diretamente no PDV (avulsa ou check-in de plano).
     */
    public function nomeTitular(): ?string
    {
        return $this->cliente?->nome
            ?? $this->dependente?->nome
            ?? $this->carteirinha?->cliente?->nome
            ?? $this->carteirinha?->dependente?->nome
            ?? $this->observacao;
    }

    public function precisaValidacao(): bool
    {
        return in_array($this->origem, self::ORIGENS_QUE_EXIGEM_VALIDACAO, true);
    }

    public function jaValidado(): bool
    {
        return $this->validado_em !== null;
    }

    /**
     * "Válido até" cobre o dia inteiro (não expira à meia-noite do próprio
     * dia) — só considera expirado a partir do dia seguinte.
     */
    public function estaExpirado(): bool
    {
        return $this->validade_ate !== null && $this->validade_ate->isBefore(now()->startOfDay());
    }

    /**
     * Token opaco embutido no QR do voucher — só gerado para origens
     * remotas (ver ORIGENS_QUE_EXIGEM_VALIDACAO). Único por linha de acessos,
     * nunca reaproveitado, então um mesmo voucher só passa na portaria uma vez.
     */
    public static function gerarCodigoValidacao(): string
    {
        do {
            $codigo = 'VCH-'.Str::upper(Str::random(20));
        } while (self::where('codigo_validacao', $codigo)->exists());

        return $codigo;
    }
}
