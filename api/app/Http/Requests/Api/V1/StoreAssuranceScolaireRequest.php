<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssuranceScolaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'annee_scolaire_id' => ['required', 'integer', 'exists:annee_scolaires,id'],
            'libelle' => ['required', 'string', 'max:100'],
            'effectif' => ['required', 'integer', 'min:0', 'max:99999'],
            'nom_assureur' => ['nullable', 'string', 'max:150'],
            'numero_police' => ['nullable', 'string', 'max:100'],
        ];
    }
}
