<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompetenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label_fr' => $this->label_fr,
            'label_en' => $this->label_en,
            'abbreviation' => $this->abbreviation,
            'notation' => $this->notation,
            'evalue_pratique' => (bool) $this->evalue_pratique,
            'volets' => $this->volets(),
            'repartition_volets' => $this->repartitionVolets(),
            'ordre' => $this->ordre,
            'statut' => $this->statut,
            'matieres_count' => $this->whenCounted('matieres'),
            'classes_count' => $this->whenCounted('classeCompetences'),
            // Le contenu enseigné au titre de la compétence : ce que l'écran
            // d'attribution montre pour dire ce qu'un bloc recouvre.
            'matieres' => $this->whenLoaded('matieres', fn () => $this->matieres->map(fn ($matiere) => [
                'id' => $matiere->id,
                'nom' => $matiere->nom,
                'nom_en' => $matiere->nom_en,
                'abbreviation' => $matiere->abbreviation,
            ])->values()),
            'school_id' => $this->school_id,
            'school' => $this->whenLoaded('school', fn () => $this->school ? [
                'id' => $this->school->id,
                'name' => $this->school->name,
                'code' => $this->school->code,
                'type' => $this->school->type,
            ] : null),
        ];
    }
}
