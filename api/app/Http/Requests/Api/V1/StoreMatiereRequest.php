<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreMatiereRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Accessible uniquement via App\Support\Tenant::schoolIds() côté
            // contrôleur — un id hors du périmètre du compte y est rejeté (403)
            // plutôt qu'ici, où on ne connaît que l'existence de l'école.
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'nom' => ['required', 'string', 'max:100'],
            'nom_en' => ['nullable', 'string', 'max:100'],
            'abbreviation' => ['nullable', 'string', 'max:20'],
            'departement_id' => ['nullable', $this->scopedExists('departements')],
            // Primaire et maternelle : barème de la matière (archange borne
            // la saisie entre 10 et 100) et présence du volet pratique.
            'notation' => ['nullable', 'integer', 'min:10', 'max:100'],
            'evalue_pratique' => ['nullable', 'boolean'],
            // Répartition du barème entre les volets (oral, écrit, savoir-être,
            // pratique) : facultative, mais si fournie doit couvrir exactement
            // les volets de la matière et sommer au barème (cf. withValidator).
            'repartition_volets' => ['nullable', 'array'],
            'repartition_volets.oral' => ['required_with:repartition_volets', 'numeric', 'min:0'],
            'repartition_volets.ecrit' => ['required_with:repartition_volets', 'numeric', 'min:0'],
            'repartition_volets.savoir_etre' => ['required_with:repartition_volets', 'numeric', 'min:0'],
            'repartition_volets.pratique' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $repartition = $this->input('repartition_volets');

            if ($repartition === null) {
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
                    "Le volet pratique n'est pas évalué pour cette matière."
                );

                return;
            }

            $composantes = $evaluePratique
                ? ['oral', 'ecrit', 'savoir_etre', 'pratique']
                : ['oral', 'ecrit', 'savoir_etre'];

            $somme = array_sum(array_intersect_key($repartition, array_flip($composantes)));
            $notation = (float) ($this->input('notation') ?? 20);

            if (abs($somme - $notation) > 0.01) {
                $validator->errors()->add(
                    'repartition_volets',
                    "La somme des volets ({$somme}) doit être égale au barème ({$notation})."
                );
            }
        });
    }
}
