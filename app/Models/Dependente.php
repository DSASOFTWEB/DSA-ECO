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
    /**
     * Parentesco do cadastro na forma da lista atual. Cadastros antigos
     * (texto livre, ex.: "ESPOSA", "FILHA") são reconhecidos para o
     * formulário de edição abrir com a opção certa; desconhecido = null.
     */
    public function parentescoNaLista(): ?string
    {
        $atual = (string) $this->parentesco;
        if (in_array($atual, self::niveisParentesco(), true)) {
            return $atual;
        }

        $chave = \Illuminate\Support\Str::of($atual)->ascii()->lower()->replaceMatches('/[^a-z]/', '')->toString();

        return match (true) {
            $chave === '' => null,
            in_array($chave, ['esposa', 'esposo', 'marido', 'mulher', 'conjuge', 'companheiro', 'companheira'], true) => self::PARENTESCO_CONJUGE,
            str_starts_with($chave, 'filh') || str_starts_with($chave, 'enteado') => self::PARENTESCO_FILHO,
            str_starts_with($chave, 'sobrinh') => self::PARENTESCO_SOBRINHO,
            str_starts_with($chave, 'ti') && strlen($chave) <= 5 => self::PARENTESCO_TIO,
            in_array($chave, ['pai', 'mae', 'paimae', 'padrasto', 'madrasta'], true) => self::PARENTESCO_PAI_MAE,
            str_starts_with($chave, 'irma') => self::PARENTESCO_IRMAO,
            str_starts_with($chave, 'net') => self::PARENTESCO_NETO,
            str_starts_with($chave, 'avo') => self::PARENTESCO_AVO,
            str_starts_with($chave, 'prim') => self::PARENTESCO_PRIMO,
            default => null,
        };
    }

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
