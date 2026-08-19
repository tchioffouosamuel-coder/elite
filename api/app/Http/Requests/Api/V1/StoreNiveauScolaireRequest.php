<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNiveauScolaireRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $niveauId = $this->route('id');

        return [
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('niveau_scolaires', 'code')
                    ->where('school_id', $this->schoolIdPourValidation())
                    ->ignore($niveauId),
            ],
            'libelle' => ['required', 'string', 'max:255'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:255'],
            // L'animateur doit appartenir à la même école, sinon une école
            // pourrait désigner le personnel d'une autre.
            'animateur_personnel_id' => ['nullable', $this->scopedExists('personnels')],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Ce code de niveau existe déjà dans cet établissement.',
        ];
    }
}
