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
            // Fiche de préparation : uniquement pertinente sur une leçon, mais
            // validée à plat comme le reste plutôt que par une règle conditionnelle.
            $regles[$chemin.'.*.objectifs'] = ['nullable', 'string', 'max:2000'];
            $regles[$chemin.'.*.materiel'] = ['nullable', 'string', 'max:2000'];
            $regles[$chemin.'.*.activites'] = ['nullable', 'string', 'max:2000'];
            $regles[$chemin.'.*.devoirs'] = ['nullable', 'string', 'max:2000'];
            // La séquence cible doit appartenir à l'établissement courant.
            $regles[$chemin.'.*.sequence_id'] = ['nullable', $this->scopedExistsSequence()];

            /*
             * Les seize champs de la fiche, saisis directement dans la
             * progression. Bornés large : ce sont des zones de texte libre que
             * l'enseignant remplit à sa main, parfois sur plusieurs lignes.
             */
            foreach (ProgressionItem::CHAMPS_FICHE as $champ) {
                $regles[$chemin.'.*.'.$champ] = $champ === 'mode'
                    ? ['nullable', Rule::in(['digital', 'practical', 'normal'])]
                    : ['nullable', 'string', 'max:4000'];
            }

            // Repères de calendrier repris de la feuille de l'établissement.
            $regles[$chemin.'.*.term'] = ['nullable', 'string', 'max:40'];
            $regles[$chemin.'.*.mois'] = ['nullable', 'string', 'max:20'];
            $regles[$chemin.'.*.semaine'] = ['nullable', 'string', 'max:20'];
            $regles[$chemin.'.*.date_prevue'] = ['nullable', 'date'];
        }

        return $regles;
    }
}
