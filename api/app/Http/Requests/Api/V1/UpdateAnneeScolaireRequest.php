<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnneeScolaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** `is_active` reste hors de cette route : `activate()` porte seule cette bascule. */
    public function rules(): array
    {
        return [
            'libelle' => [
                'required', 'string', 'max:20',
                Rule::unique('annee_scolaires')->where('school_id', app('tenant.school_id'))->ignore($this->route('id')),
            ],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
        ];
    }

    public function messages(): array
    {
        return [
            'libelle.unique' => 'Une année scolaire porte déjà ce libellé pour cet établissement.',
        ];
    }
}
