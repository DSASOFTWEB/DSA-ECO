<?php

namespace App\Http\Requests\Terminal;

use App\Models\Empresa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('terminal')
            ? $this->user()->can('terminais.editar')
            : $this->user()->can('terminais.criar');
    }

    protected function prepareForValidation(): void
    {
        // Série em branco = usa a série padrão da empresa.
        $this->merge([
            'serie_nfce' => $this->filled('serie_nfce') ? $this->input('serie_nfce') : null,
            'numero_ultima_nfce_producao' => $this->input('numero_ultima_nfce_producao') ?: 0,
            'numero_ultima_nfce_homologacao' => $this->input('numero_ultima_nfce_homologacao') ?: 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:100'],
            'unidade_id' => ['required', Rule::exists('unidades', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'status' => ['required', 'in:ativo,inativo'],
            'serie_nfce' => ['nullable', 'integer', 'min:1', 'max:999',
                Rule::unique('terminais', 'serie_nfce')->where('empresa_id', $this->user()->empresa_id)->ignore($this->route('terminal'))],
            'numero_ultima_nfce_producao' => ['integer', 'min:0', 'max:999999999'],
            'numero_ultima_nfce_homologacao' => ['integer', 'min:0', 'max:999999999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $serie = $this->input('serie_nfce');
            $seriePadrao = (int) (Empresa::find($this->user()->empresa_id)?->numero_serie_nfce ?: 1);

            if ($serie !== null && (int) $serie === $seriePadrao) {
                $validator->errors()->add('serie_nfce', "A série {$seriePadrao} já é a série padrão da empresa. Deixe em branco para usá-la ou escolha outra.");
            }
        });
    }

    public function messages(): array
    {
        return [
            'serie_nfce.unique' => 'Esta série já está em uso por outro terminal.',
        ];
    }
}
