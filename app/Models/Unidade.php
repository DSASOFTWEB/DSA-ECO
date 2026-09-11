<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unidade extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'empresa_id', 'nome', 'cnpj', 'cep', 'endereco', 'numero', 'complemento',
        'bairro', 'cidade', 'uf', 'telefone', 'capacidade_maxima', 'status',
    ];

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function caixas(): HasMany
    {
        return $this->hasMany(Caixa::class);
    }

    public function terminais(): HasMany
    {
        return $this->hasMany(Terminal::class);
    }

    public function acessos(): HasMany
    {
        return $this->hasMany(Acesso::class);
    }

    public function quartos(): HasMany
    {
        return $this->hasMany(Quarto::class);
    }

    public function hospedagens(): HasMany
    {
        return $this->hasMany(Hospedagem::class);
    }

    public function pontosAtendimento(): HasMany
    {
        return $this->hasMany(PontoAtendimento::class);
    }

    public function scopeAtivas($query)
    {
        return $query->where('status', 'ativo');
    }
}
