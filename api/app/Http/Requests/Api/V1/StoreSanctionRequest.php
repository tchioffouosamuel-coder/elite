<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;

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
            'type' => ['required', 'in:corvee,exclusion_temporaire,exclusion_definitive,autre'],
            'duree_jours' => ['nullable', 'integer', 'min:1', 'max:255'],
            'motif' => ['required', 'string'],
            'date_sanction' => ['required', 'date'],
        ];
    }
}
