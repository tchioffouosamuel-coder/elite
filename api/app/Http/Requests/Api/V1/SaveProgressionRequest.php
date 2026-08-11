<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use App\Models\ProgressionItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProgressionRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * L'arbre ne dépasse pas trois niveaux (module → chapitre → leçon) : les
     * règles sont donc écrites à plat plutôt que par une validation récursive,
     * plus difficile à lire pour un gain nul.
     */
    public function rules(): array
    {
        $niveaux = ['items', 'items.*.enfants', 'items.*.enfants.*.enfants'];
        $regles = ['items' => ['present', 'array']];

        foreach ($niveaux as $chemin) {
            $regles[$chemin] = ['nullable', 'array'];
            $regles[$chemin.'.*.id'] = ['nullable', 'integer'];
            $regles[$chemin.'.*.type'] = ['required', Rule::in(ProgressionItem::TYPES)];
            $regles[$chemin.'.*.titre'] = ['required', 'string', 'max:255'];
            $regles[$chemin.'.*.description'] = ['nullable', 'string', 'max:2000'];
            $regles[$chemin.'.*.duree_prevue'] = ['nullable', 'integer', 'min:1', 'max:200'];
            // La séquence cible doit appartenir à l'établissement courant.
            $regles[$chemin.'.*.sequence_id'] = ['nullable', $this->scopedExistsSequence()];
        }

        return $regles;
    }
}
