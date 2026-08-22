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
            // Accessible uniquement via App\Support\Tenant::schoolIds() côté
            // contrôleur — un id hors du périmètre du compte y est rejeté (403)
            // plutôt qu'ici, où on ne connaît que l'existence de l'école.
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'nom' => ['required', 'string', 'max:100'],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'abbreviation' => ['nullable', 'string', 'max:20'],
            'departement_id' => ['nullable', $this->scopedExists('departements')],
            // Compétence dont la matière est un contenu — primaire et
            // maternelle. Au secondaire la matière est elle-même l'unité notée
            // et n'en releve d'aucune, d'ou le caractere facultatif.
            'competence_id' => ['nullable', $this->scopedExists('competences')],
        ];
    }
}
