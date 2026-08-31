<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreRapportTrimestreTexteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trimestre_id' => ['required', 'integer', 'exists:trimestres,id'],
            'contenu' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
