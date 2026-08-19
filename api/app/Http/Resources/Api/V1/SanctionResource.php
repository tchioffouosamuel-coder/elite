<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SanctionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eleve' => $this->whenLoaded('eleve', fn () => [
                'id' => $this->eleve->id,
                'nom_complet' => $this->eleve->nom_complet,
            ]),
            'classe' => $this->whenLoaded('classe', fn () => $this->classe->nom),
            'school' => $this->when($this->relationLoaded('eleve') && $this->eleve?->relationLoaded('school'), fn () => $this->eleve->school ? [
                'id' => $this->eleve->school->id,
                'name' => $this->eleve->school->name,
                'code' => $this->eleve->school->code,
                'type' => $this->eleve->school->type,
            ] : null),
            'type' => $this->type,
            'duree_jours' => $this->duree_jours,
            'date_debut' => $this->date_debut?->format('Y-m-d'),
            'date_fin' => $this->date_fin?->format('Y-m-d'),
            'motif' => $this->motif,
            'commentaire' => $this->commentaire,
            'date_sanction' => $this->date_sanction?->format('Y-m-d'),
            'statut' => $this->statut,
            'impacte_bulletin' => $this->impacte_bulletin,
            'enregistre_par' => $this->enregistrePar?->nom_complet,
        ];
    }
}
