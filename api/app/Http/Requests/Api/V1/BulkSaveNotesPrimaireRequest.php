<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use App\Models\Note;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkSaveNotesPrimaireRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['required', 'array', 'min:1'],
            'notes.*.eleve_id' => ['required', $this->scopedExists('eleves')],
            'notes.*.sequence_id' => ['required', $this->scopedExistsSequence()],
            'notes.*.composante' => ['required', Rule::in(Note::COMPOSANTES_PRIMAIRE)],
            // Le plafond dépend du barème de la compétence : il est vérifié dans
            // le contrôleur, qui connaît la compétence visée.
            'notes.*.valeur' => ['nullable', 'numeric', 'min:0'],
            // Maternelle : le volet porte un niveau d'appréciation au lieu d'une
            // note. Le service borne l'identifiant au référentiel de l'école.
            'notes.*.appreciation_id' => ['nullable', 'integer', 'exists:appreciations,id'],
        ];
    }
}
