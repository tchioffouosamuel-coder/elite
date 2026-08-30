<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreConseilEcoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee_scolaire_id' => ['required', 'integer', 'exists:annee_scolaires,id'],
            'existe' => ['required', 'boolean'],
            'date_ag_elective' => ['nullable', 'date'],
            'duree_mandat' => ['nullable', 'string', 'max:50'],
            'fin_mandat' => ['nullable', 'digits:4'],
            'president_nom' => ['nullable', 'string', 'max:200'],
            'president_fonction' => ['nullable', 'string', 'max:150'],
            'president_telephone' => ['nullable', 'string', 'max:30'],
            'statut_projet_ecole' => ['nullable', 'string', 'max:150'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
