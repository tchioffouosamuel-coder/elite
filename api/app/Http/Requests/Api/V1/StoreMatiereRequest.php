<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreMatiereRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'abbreviation' => ['nullable', 'string', 'max:20'],
            'departement_id' => ['nullable', $this->scopedExists('departements')],
            // Primaire et maternelle : barème de la matière (archange borne
            // la saisie entre 10 et 100) et présence du volet pratique.
            'notation' => ['nullable', 'integer', 'min:10', 'max:100'],
            'evalue_pratique' => ['nullable', 'boolean'],
        ];
    }
}
