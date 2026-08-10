<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatiereResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'nom_en' => $this->nom_en,
            'abbreviation' => $this->abbreviation,
            'notation' => $this->notation,
            'evalue_pratique' => (bool) $this->evalue_pratique,
            'composantes' => $this->composantes(),
            'statut' => $this->statut,
            'departement' => $this->whenLoaded('departement', fn () => $this->departement ? [
                'id' => $this->departement->id,
                'nom' => $this->departement->nom,
            ] : null),
        ];
    }
}
