<?php

namespace App\Http\Requests\Marca;

use App\Models\Marca;
use Illuminate\Foundation\Http\FormRequest;

class StoreMarcaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $marca = $this->route('marca');

        return $marca ? $this->user()->can('update', $marca) : $this->user()->can('create', Marca::class);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
        ];
    }
}
