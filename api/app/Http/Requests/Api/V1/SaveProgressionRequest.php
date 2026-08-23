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

            /*
             * Champs de la fiche, communs aux deux gabarits (primaire/
             * maternelle et secondaire) — cf. ProgressionItem::CHAMPS_FICHE.
             * Bornés large : ce sont des zones de texte libre que l'enseignant
             * remplit à sa main, parfois sur plusieurs lignes.
             */
            foreach (ProgressionItem::CHAMPS_FICHE as $champ) {
                $regles[$chemin.'.*.'.$champ] = ['nullable', 'string', 'max:4000'];
            }

            // Repères de calendrier de la ligne : Week, Date Planned, Date
            // Taught, Duration/Periods.
            $regles[$chemin.'.*.semaine'] = ['nullable', 'string', 'max:20'];
            $regles[$chemin.'.*.date_prevue'] = ['nullable', 'date'];
            $regles[$chemin.'.*.date_realisee'] = ['nullable', 'date'];
            $regles[$chemin.'.*.duree'] = ['nullable', 'string', 'max:50'];

            // Valeurs des colonnes libres de la matière (cf. ProgressionColonne),
            // indexées par l'id de la colonne — dynamique, donc validées comme
            // un tableau générique plutôt que par clé nommée.
            $regles[$chemin.'.*.colonnes_libres'] = ['nullable', 'array'];
            $regles[$chemin.'.*.colonnes_libres.*'] = ['nullable', 'string', 'max:1000'];
        }

        return $regles;
    }
}
