<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ncm extends Model
{
    protected $fillable = [
        'ncm', 'ex', 'descricao', 'fonte',
    ];
}
