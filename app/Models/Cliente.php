<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Cliente extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'empresa_id', 'unidade_id', 'nome', 'cpf', 'rg', 'data_nascimento',
        'email', 'telefone', 'whatsapp', 'cep', 'endereco', 'numero',
        'complemento', 'bairro', 'cidade', 'uf', 'foto_path', 'status', 'observacoes',
    ];

    protected $casts = [
        'data_nascimento' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function dependentes(): HasMany
    {
        return $this->hasMany(Dependente::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function contratoAtivo(): HasOne
    {
        return $this->hasOne(Contrato::class)->where('status', 'ativo')->latestOfMany();
    }

    public function carteirinha(): HasOne
    {
        return $this->hasOne(Carteirinha::class);
    }

    public function vendas(): HasMany
    {
        return $this->hasMany(Venda::class);
    }

    public function hospedagens(): HasMany
    {
        return $this->hasMany(Hospedagem::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('status', 'ativo');
    }

    public function fotoUrl(): ?string
    {
        return $this->foto_path ? Storage::disk('public')->url($this->foto_path) : null;
    }

    /**
     * Número usado pelo canal customizado de WhatsApp (ver
     * App\Notifications\Channels\WhatsappChannel). Cai para o telefone
     * comum quando o cliente não tem um WhatsApp cadastrado separadamente.
     */
    public function routeNotificationForWhatsapp(): ?string
    {
        return $this->whatsapp ?: $this->telefone;
    }
}
