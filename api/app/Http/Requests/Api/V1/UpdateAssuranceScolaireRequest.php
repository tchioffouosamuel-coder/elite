<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssuranceScolaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'libelle' => ['sometimes', 'required', 'string', 'max:100'],
            'effectif' => ['sometimes', 'required', 'integer', 'min:0', 'max:99999'],
            'nom_assureur' => ['nullable', 'string', 'max:150'],
            'numero_police' => ['nullable', 'string', 'max:100'],
        ];
    }
}
