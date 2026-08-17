<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisiteInfirmerieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eleve' => $this->whenLoaded('eleve', fn () => [
                'id' => $this->eleve->id,
                'nom_complet' => $this->eleve->nom_complet,
            ]),
            'classe' => $this->whenLoaded('classe', fn () => $this->classe ? [
                'id' => $this->classe->id,
                'nom' => $this->classe->nom,
            ] : null),
            'date_visite' => $this->date_visite?->format('Y-m-d\TH:i'),
            'raison' => $this->raison,
            'soins_prodiges' => $this->soins_prodiges,
            'cout_soins' => $this->cout_soins,
            'observations' => $this->observations,
            'enregistre_par' => $this->enregistrePar?->nom_complet,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
