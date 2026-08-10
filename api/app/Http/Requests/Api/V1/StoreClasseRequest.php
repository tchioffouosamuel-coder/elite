<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreClasseRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'niveau_id' => ['required', 'exists:niveaux,id'], // référentiel global, non scopé
            // Niveau d'enseignement propre à l'établissement (SIL, CP…) au
            // primaire et en maternelle ; absent au secondaire.
            'niveau_scolaire_id' => ['nullable', $this->scopedExists('niveau_scolaires')],
            'annee_scolaire_id' => ['required', $this->scopedExists('annee_scolaires')],
            'professeur_principal_id' => ['nullable', $this->scopedExists('personnels')],
            // Enseignant unique de la classe au primaire et en maternelle.
            'titulaire_id' => ['nullable', $this->scopedExists('personnels')],
            'surveillant_general_id' => ['nullable', $this->scopedExists('personnels')],
            'censeur_id' => ['nullable', $this->scopedExists('personnels')],
            'conseiller_orientation_id' => ['nullable', $this->scopedExists('personnels')],
            'nom' => ['required', 'string', 'max:50'],
            'filiere' => ['nullable', 'string', 'max:100'],
            // Non vide = classe présentant un examen officiel (BEPC, BAC, CEP…).
            'code_examen' => ['nullable', 'string', 'max:40'],
            'capacite' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
