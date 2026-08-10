<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClasseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'filiere' => $this->filiere,
            'capacite' => $this->capacite,
            'effectif' => $this->when(isset($this->eleves_count), $this->eleves_count),
            'niveau' => $this->whenLoaded('niveau', fn () => [
                'id' => $this->niveau?->id,
                'code' => $this->niveau?->code,
                'name_fr' => $this->niveau?->name_fr,
            ]),
            'professeur_principal' => $this->whenLoaded('professeurPrincipal', fn () => $this->professeurPrincipal ? [
                'id' => $this->professeurPrincipal->id,
                'nom_complet' => $this->professeurPrincipal->nomComplet(),
            ] : null),
        ];
    }
}
