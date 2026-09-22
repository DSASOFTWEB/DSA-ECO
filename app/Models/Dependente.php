<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Dependente extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const PARENTESCO_FILHO = 'Filho(a)';

    public const PARENTESCO_SOBRINHO = 'Sobrinho(a)';

    public const PARENTESCO_TIO = 'Tio(a)';

    public const PARENTESCO_CONJUGE = 'Cônjuge';

    public const PARENTESCO_PAI_MAE = 'Pai/Mãe';

    public const PARENTESCO_IRMAO = 'Irmão(ã)';

    public const PARENTESCO_NETO = 'Neto(a)';

    public const PARENTESCO_AVO = 'Avô/Avó';

    public const PARENTESCO_PRIMO = 'Primo(a)';

    public const PARENTESCO_OUTRO = 'Outro';

    protected $fillable = [
        'cliente_id', 'nome', 'cpf', 'data_nascimento', 'parentesco', 'foto_path', 'status',
    ];

    protected $casts = [
        'data_nascimento' => 'date',
    ];

    /**
     * Graus de parentesco exibidos no cadastro de dependente.
     *
     * @return list<string>
     */
    public static function niveisParentesco(): array
    {
        return [
            self::PARENTESCO_FILHO,
            self::PARENTESCO_SOBRINHO,
            self::PARENTESCO_TIO,
            self::PARENTESCO_CONJUGE,
            self::PARENTESCO_PAI_MAE,
            self::PARENTESCO_IRMAO,
            self::PARENTESCO_NETO,
            self::PARENTESCO_AVO,
            self::PARENTESCO_PRIMO,
            self::PARENTESCO_OUTRO,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contratos(): BelongsToMany
    {
        return $this->belongsToMany(Contrato::class, 'contrato_dependente');
    }

    public function carteirinha(): HasOne
    {
        return $this->hasOne(Carteirinha::class);
    }
}
