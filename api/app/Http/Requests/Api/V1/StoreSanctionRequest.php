<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use App\Models\Sanction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSanctionRequest extends FormRequest
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
            'trimestre_id' => ['required', $this->scopedExistsTrimestre()],
            'type' => ['required', Rule::in(Sanction::TYPES)],
            // Exigée uniquement pour une exclusion temporaire : une corvée ou
            // un blâme n'ont pas de durée à borner.
            'duree_jours' => ['nullable', 'integer', 'min:1', 'max:255', 'required_if:type,exclusion_temporaire'],
            'motif' => ['required', 'string', 'min:10'],
            'commentaire' => ['nullable', 'string'],
            'date_sanction' => ['required', 'date'],
            'impacte_bulletin' => ['nullable', 'boolean'],
        ];
    }
}
