<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'empresa_id', 'unidade_id', 'name', 'email', 'telefone', 'cpf',
        'password', 'status', 'percentual_comissao', 'percentual_comissao_reativacao',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_login_at' => 'datetime',
            'password' => 'hashed',
            'percentual_comissao' => 'decimal:2',
            'percentual_comissao_reativacao' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'status', 'unidade_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    /**
     * Vendas registradas por este usuário quando atua como vendedor.
     */
    public function vendas()
    {
        return $this->hasMany(Venda::class, 'vendedor_id');
    }

    public function comissoes()
    {
        return $this->hasMany(Comissao::class, 'vendedor_id');
    }

    public function scopeAtivos($query)
    {
        return $query->where('status', 'ativo');
    }
}
