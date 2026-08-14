<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Degré d'enseignement propre à l'établissement (SIL, CP…) : le
            // primaire seul s'organise ainsi. Le secondaire suit ses
            // départements, et la maternelle ne connaît pas cette notion — ses
            // sections se suffisent à elles-mêmes.
            'niveau_scolaire_id' => [
                'nullable',
                Rule::prohibitedIf(fn() => ! $this->ecoleUtiliseNiveaux()),
                $this->scopedExists('niveau_scolaires'),
            ],
            'annee_scolaire_id' => ['required', $this->scopedExists('annee_scolaires')],
            'sous_systeme_id' => ['nullable', $this->scopedExists('sous_systemes')],
            'professeur_principal_id' => ['nullable', $this->scopedExists('personnels')],
            // Enseignant unique de la classe au primaire et en maternelle.
            'titulaire_id' => ['nullable', $this->scopedExists('personnels')],
            'surveillant_general_id' => ['nullable', $this->scopedExists('personnels')],
            'censeur_id' => ['nullable', $this->scopedExists('personnels')],
            'conseiller_orientation_id' => ['nullable', $this->scopedExists('personnels')],
            'nom' => ['required', 'string', 'max:50'],
            'sigle' => ['nullable', 'string', 'max:20'],
            'niveau_classe' => ['nullable', 'string', 'max:100'],
            'filiere' => ['nullable', 'string', 'max:100'],
            // Non vide = classe présentant un examen officiel (BEPC, BAC, CEP…).
            'code_examen' => ['nullable', 'string', 'max:40'],
            'capacite' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'niveau_scolaire_id.prohibited' => "Cet établissement ne s'organise pas en niveaux d'enseignement.",
        ];
    }

    /** Seul le primaire range ses classes par degré d'enseignement. */
    private function ecoleUtiliseNiveaux(): bool
    {
        return School::find(app('tenant.school_id'))?->type === 'primaire';
    }
}
