<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use App\Models\Revendication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRevendicationRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'eleve_id' => ['required', $this->scopedExists('eleves')],
            // classe_matiere_id n'a pas de colonne school_id directe : sa
            // portée est vérifiée dans le contrôleur, comme pour les
            // évaluations (cf. EvaluationController::affectation()).
            'classe_matiere_id' => ['nullable', 'integer', 'required_if:type,note'],
            'trimestre_id' => ['nullable', $this->scopedExistsTrimestre()],
            'type' => ['required', Rule::in(Revendication::TYPES)],
            'objet' => ['required', 'string', 'max:255'],
            'motif' => ['required', 'string', 'min:10'],
            'date_reception' => ['required', 'date'],
        ];
    }
}
