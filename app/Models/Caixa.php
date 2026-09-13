<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Caixa extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'empresa_id', 'unidade_id', 'terminal_id', 'usuario_abertura_id', 'usuario_fechamento_id',
        'data_abertura', 'data_fechamento', 'valor_abertura', 'valor_fechamento_informado',
        'valor_fechamento_sistema', 'diferenca', 'status', 'observacoes',
    ];

    protected $casts = [
        'data_abertura' => 'datetime',
        'data_fechamento' => 'datetime',
        'valor_abertura' => 'decimal:2',
        'valor_fechamento_informado' => 'decimal:2',
        'valor_fechamento_sistema' => 'decimal:2',
        'diferenca' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function usuarioAbertura(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_abertura_id');
    }

    public function usuarioFechamento(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_fechamento_id');
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(CaixaMovimentacao::class);
    }

    public function vendas(): HasMany
    {
        return $this->hasMany(Venda::class);
    }

    public function estaAberto(): bool
    {
        return $this->status === 'aberto';
    }
}
