<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class BulkSaveAppreciationsSequenceRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sequence_id' => ['required', $this->scopedExistsSequence()],
            'appreciations' => ['required', 'array', 'min:1'],
            'appreciations.*.eleve_id' => ['required', $this->scopedExists('eleves')],
            // 60 caractères : bulletin imprimé, pas un champ de texte libre.
            'appreciations.*.texte' => ['nullable', 'string', 'max:60'],
        ];
    }
}
