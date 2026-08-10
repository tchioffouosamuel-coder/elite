<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreEleveRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'classe_id' => ['nullable', $this->scopedExists('classes')],
            'matricule' => ['nullable', 'string', 'max:50'],
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'sexe' => ['required', 'in:M,F'],
            'date_naissance' => ['nullable', 'date'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'nationalite' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'redoublant' => ['nullable', 'boolean'],

            'tuteurs' => ['nullable', 'array'],
            'tuteurs.*.nom' => ['required_with:tuteurs', 'string', 'max:100'],
            'tuteurs.*.prenom' => ['required_with:tuteurs', 'string', 'max:100'],
            'tuteurs.*.telephone' => ['nullable', 'string', 'max:30'],
            'tuteurs.*.email' => ['nullable', 'email', 'max:150'],
            'tuteurs.*.lien_parente' => ['nullable', 'string', 'max:50'],
            'tuteurs.*.is_principal' => ['nullable', 'boolean'],
        ];
    }
}
