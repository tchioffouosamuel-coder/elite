<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class BulkSaveNotesRequest extends FormRequest
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
            'notes' => ['required', 'array', 'min:1'],
            'notes.*.eleve_id' => ['required', $this->scopedExists('eleves')],
            'notes.*.valeur' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ];
    }
}
