<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class BulkSaveAbsencesRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trimestre_id' => ['required', $this->scopedExistsTrimestre()],
            'absences' => ['required', 'array', 'min:1'],
            'absences.*.eleve_id' => ['required', $this->scopedExists('eleves')],
            'absences.*.heures_justifiees' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'absences.*.heures_non_justifiees' => ['nullable', 'numeric', 'min:0', 'max:999'],
        ];
    }
}
