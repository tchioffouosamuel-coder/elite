<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class StorePersonnelRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom_complet' => ['required', 'string', 'max:200'],
            'fonction_id' => ['required', $this->scopedExists('fonction_referentiel')],
            'departement_id' => ['nullable', $this->scopedExists('departements')],
            'matricule' => ['nullable', 'string', 'max:50'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'date_embauche' => ['nullable', 'date'],
        ];
    }
}
