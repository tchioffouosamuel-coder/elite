<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Personnel;
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
            // `personnel_id` dit ce qui est stocké (nul = pas de délégation),
            // `enseignant` dit qui tient réellement la compétence — le
            // titulaire tant que personne n'a été nommé. L'écran a besoin des
            // deux : l'un pour le formulaire, l'autre pour la colonne.
            'personnel_id' => $this->personnel_id,
            'enseignant' => $this->enseignantAffiche(),
            'notation' => $this->notation,
            'evalue_pratique' => (bool) $this->evalue_pratique,
            'volets' => $this->volets(),
            'repartition_volets' => $this->repartitionVolets(),
            'groupe' => $this->groupe,
            'statut' => $this->statut,
        ];
    }

    /**
     * Enseignant à afficher, et d'où il vient : nommé sur l'attribution, ou
     * hérité du titulaire de la classe. Sans l'origine, l'écran ne pourrait
     * pas distinguer une délégation d'un simple héritage.
     *
     * @return array{id: int, nom_complet: string, herite: bool}|null
     */
    private function enseignantAffiche(): ?array
    {
        $personnel = $this->personnel_id !== null
            ? ($this->relationLoaded('enseignant') ? $this->enseignant : null)
            : ($this->relationLoaded('classe') ? $this->classe?->titulaire : null);

        if (! $personnel instanceof Personnel) {
            return null;
        }

        return [
            'id' => $personnel->id,
            'nom_complet' => $personnel->nom_complet,
            'herite' => $this->personnel_id === null,
        ];
    }
}
