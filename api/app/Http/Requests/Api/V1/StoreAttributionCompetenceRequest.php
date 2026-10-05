<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Classe;
use App\Models\ClasseCompetence;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Barème d'une compétence dans une classe — réglage porté par l'attribution,
 * pas par la compétence.
 *
 * Sert aux deux bouts du geste : `attribuer()`, où les valeurs s'appliquent à
 * toutes les compétences du lot, et `modifierAttribution()`, où elles portent
 * sur une attribution précise. Les règles sont les mêmes des deux côtés, d'où
 * la classe partagée ; seul l'identifiant de la classe se lit différemment
 * ({@see classe()}).
 */
class StoreAttributionCompetenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Barème dans cette classe — borne la saisie entre 5 et 100.
            // Facultatif en maternelle, qui évalue par appréciation et n'a donc
            // ni barème ni répartition de volets à renseigner. Facultatif aussi
            // à l'attribution : on pose le bloc d'abord, on le règle ensuite.
            'notation' => ['nullable', 'integer', 'min:5', 'max:100'],
            'evalue_pratique' => ['nullable', 'boolean'],
            'groupe' => ['nullable', 'integer', 'min:1', 'max:9'],

            // Enseignant responsable de la compétence dans cette classe.
            // `null` est une valeur à part entière : elle rend la compétence
            // au titulaire, et c'est le seul moyen de défaire une délégation.
            'personnel_id' => ['nullable', 'integer', 'exists:personnels,id'],
            'statut' => ['nullable', 'in:actif,inactif'],

            // Répartition du barème entre les volets : chaque volet est
            // facultatif, y compris quand on en renseigne un autre — celui
            // qu'on laisse vide compte pour 0 point
            // (cf. ClasseCompetence::repartitionVolets).
            'repartition_volets' => ['nullable', 'array'],
            'repartition_volets.oral' => ['nullable', 'numeric', 'min:0'],
            'repartition_volets.ecrit' => ['nullable', 'numeric', 'min:0'],
            'repartition_volets.savoir_etre' => ['nullable', 'numeric', 'min:0'],
            'repartition_volets.pratique' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $repartition = $this->input('repartition_volets');

            if ($repartition === null || $this->parAppreciation()) {
                return;
            }

            // Le seul garde-fou qui reste : un volet non évalué ne doit pas
            // porter de points, sans quoi le bulletin en tiendrait compte
            // malgré la case décochée. Chaque volet renseigné est sinon
            // libre — y compris de ne pas sommer au barème, celui qu'on
            // laisse vide comptant simplement pour 0
            // (cf. ClasseCompetence::repartitionVolets).
            if (! $this->boolean('evalue_pratique') && (float) ($repartition['pratique'] ?? 0) > 0) {
                $validator->errors()->add(
                    'repartition_volets.pratique',
                    "Le volet pratique n'est pas évalué pour cette compétence dans cette classe."
                );
            }
        });
    }

    /**
     * Attributs du barème seuls, les clés absentes de la requête écartées :
     * une mise à jour partielle (le seul groupe, par exemple) ne doit pas
     * remettre le barème à null au passage.
     *
     * @return array<string, mixed>
     */
    public function bareme(): array
    {
        return array_intersect_key(
            $this->validated(),
            array_flip(['notation', 'evalue_pratique', 'repartition_volets']),
        );
    }

    /**
     * La maternelle coche un visage : ni barème, ni répartition à valider.
     *
     * La classe se nomme différemment selon le bout : `{classeId}` à
     * l'attribution, `{id}` d'une attribution existante à la modification.
     */
    private function parAppreciation(): bool
    {
        $classe = match (true) {
            $this->route('classeId') !== null => Classe::whereKey($this->route('classeId'))->first(),
            $this->route('id') !== null => ClasseCompetence::whereKey($this->route('id'))->first()?->classe,
            default => null,
        };

        return $classe?->school?->type === 'maternelle';
    }
}
