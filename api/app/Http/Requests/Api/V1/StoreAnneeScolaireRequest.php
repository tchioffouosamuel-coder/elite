<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnneeScolaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Reprend la contrainte d'unicité `annee_scolaires_school_id_libelle_unique` :
            // sans cette règle, un libellé déjà pris ne se découvrait qu'à
            // l'échec de l'insertion, avec l'erreur SQL brute renvoyée telle
            // quelle au lieu d'un message de validation exploitable.
            'libelle' => [
                'required', 'string', 'max:20',
                Rule::unique('annee_scolaires')->where('school_id', app('tenant.school_id')),
            ],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'libelle.unique' => 'Une année scolaire porte déjà ce libellé pour cet établissement.',
        ];
    }
}
