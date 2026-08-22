<?php

namespace App\Http\Requests\Api\V1;

use App\Models\School;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création et modification d'une compétence évaluée.
 *
 * Les règles de barème viennent de `StoreMatiereRequest`, qu'elles ont quitté
 * en même temps que les colonnes : c'est désormais la compétence qui porte la
 * notation et la répartition des volets.
 */
class StoreCompetenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Résolue via App\Support\Tenant côté contrôleur : un id hors du
            // périmètre du compte y est rejeté (403), pas ici où l'on ne
            // connaît que l'existence de l'école.
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'label_fr' => ['required', 'string', 'max:150'],
            'label_en' => ['nullable', 'string', 'max:150'],
            'abbreviation' => ['nullable', 'string', 'max:20'],

            // Barème de la compétence — archange borne la saisie entre 10 et 100.
            // Facultatif en maternelle, qui évalue par appréciation et n'a donc
            // ni barème ni répartition de volets à renseigner.
            'notation' => [$this->parAppreciation() ? 'nullable' : 'required', 'integer', 'min:10', 'max:100'],
            'evalue_pratique' => ['nullable', 'boolean'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
            'statut' => ['nullable', 'in:actif,inactif'],

            // Répartition du barème entre les volets : facultative, mais si
            // fournie elle doit couvrir exactement les volets évalués et sommer
            // au barème (cf. withValidator).
            'repartition_volets' => ['nullable', 'array'],
            'repartition_volets.oral' => ['required_with:repartition_volets', 'numeric', 'min:0'],
            'repartition_volets.ecrit' => ['required_with:repartition_volets', 'numeric', 'min:0'],
            'repartition_volets.savoir_etre' => ['required_with:repartition_volets', 'numeric', 'min:0'],
            'repartition_volets.pratique' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** La maternelle coche un visage : ni barème, ni répartition à valider. */
    private function parAppreciation(): bool
    {
        $schoolId = $this->input('school_id') ?? app('tenant.school_id');

        return $schoolId !== null && School::whereKey($schoolId)->value('type') === 'maternelle';
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $repartition = $this->input('repartition_volets');

            if ($repartition === null || $this->parAppreciation()) {
                return;
            }

            $evaluePratique = $this->boolean('evalue_pratique');

            if ($evaluePratique && ! isset($repartition['pratique'])) {
                $validator->errors()->add(
                    'repartition_volets.pratique',
                    'La répartition du volet pratique est requise.'
                );

                return;
            }

            if (! $evaluePratique && (float) ($repartition['pratique'] ?? 0) > 0) {
                $validator->errors()->add(
                    'repartition_volets.pratique',
                    "Le volet pratique n'est pas évalué pour cette compétence."
                );

                return;
            }

            $volets = $evaluePratique
                ? ['oral', 'ecrit', 'savoir_etre', 'pratique']
                : ['oral', 'ecrit', 'savoir_etre'];

            $somme = array_sum(array_intersect_key($repartition, array_flip($volets)));
            $notation = (float) $this->input('notation');

            if (abs($somme - $notation) > 0.01) {
                $validator->errors()->add(
                    'repartition_volets',
                    "La somme des volets ({$somme}) doit être égale au barème ({$notation})."
                );
            }
        });
    }
}
