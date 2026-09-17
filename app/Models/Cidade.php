<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cidade extends Model
{
    protected $fillable = [
        'nome', 'uf', 'codigo',
    ];

    protected $appends = ['info'];

    public function getInfoAttribute(): string
    {
        return "{$this->nome} ({$this->uf})";
    }

    /**
     * @return list<string>
     */
    public static function estados(): array
    {
        return [
            'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA',
            'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN',
            'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
        ];
    }
}
