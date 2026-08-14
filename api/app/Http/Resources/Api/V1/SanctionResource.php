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
            'type' => $this->type,
            'duree_jours' => $this->duree_jours,
            'motif' => $this->motif,
            'date_sanction' => $this->date_sanction?->format('Y-m-d'),
            'enregistre_par' => $this->enregistrePar?->nom_complet,
        ];
    }
}
