<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreApeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee_scolaire_id' => ['required', 'integer', 'exists:annee_scolaires,id'],
            'legalisee' => ['required', 'boolean'],
            'date_legalisation' => ['nullable', 'date'],
            'numero_recepisse' => ['nullable', 'string', 'max:100'],
            'banque' => ['nullable', 'string', 'max:150'],
            'numero_compte' => ['nullable', 'string', 'max:100'],
            'president_nom' => ['nullable', 'string', 'max:200'],
            'president_fonction' => ['nullable', 'string', 'max:150'],
            'president_telephone' => ['nullable', 'string', 'max:30'],
            'date_ag_elective' => ['nullable', 'date'],
            'fin_mandat' => ['nullable', 'digits:4'],
            'taux_par_eleve' => ['nullable', 'integer', 'min:0'],
            'montant_percu' => ['nullable', 'integer', 'min:0'],
            'montant_depense' => ['nullable', 'integer', 'min:0'],
            'realisations' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
