<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FechamentoFiscal extends Model
{
    use BelongsToTenant;

    public const STATUS_PROCESSANDO = 'processando';

    public const STATUS_PRONTO = 'pronto';

    public const STATUS_ERRO = 'erro';

    protected $table = 'fechamentos_fiscais';

    protected $fillable = [
        'empresa_id', 'modelo', 'competencia', 'status', 'quantidade', 'valor_total',
        'arquivo_path', 'arquivo_nome', 'erro', 'gerado_por_id', 'gerado_em',
    ];

    protected $casts = [
        'competencia' => 'date',
        'valor_total' => 'decimal:2',
        'quantidade' => 'integer',
        'gerado_em' => 'datetime',
    ];

    public function geradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gerado_por_id');
    }

    public function estaPronto(): bool
    {
        return $this->status === self::STATUS_PRONTO && $this->arquivo_path && Storage::disk('local')->exists($this->arquivo_path);
    }

    public function rotuloCompetencia(): string
    {
        return $this->competencia->format('m/Y');
    }
}
