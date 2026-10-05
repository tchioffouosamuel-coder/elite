<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Attribution d'une compétence à une classe : la compétence elle-même, et
 * surtout la façon dont cette classe la note.
 *
 * Les clés de barème (`notation`, `evalue_pratique`, `volets`,
 * `repartition_volets`) gardent le nom qu'elles avaient sur
 * {@see CompetenceResource} : ce qu'elles désignent n'a pas changé, seulement
 * le niveau auquel on le règle.
 */
class ClasseCompetenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'classe_competence_id' => $this->id,
            'classe_id' => $this->classe_id,
            'competence_id' => $this->competence_id,
            'competence' => $this->whenLoaded(
                'competence',
                fn () => $this->competence ? new CompetenceResource($this->competence) : null,
            ),
            'notation' => $this->notation,
            'evalue_pratique' => (bool) $this->evalue_pratique,
            'volets' => $this->volets(),
            'repartition_volets' => $this->repartitionVolets(),
            'groupe' => $this->groupe,
            'statut' => $this->statut,
        ];
    }
}
