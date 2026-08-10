<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EleveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'nom_complet' => $this->nomComplet(),
            'sexe' => $this->sexe,
            'date_naissance' => $this->date_naissance?->format('Y-m-d'),
            'lieu_naissance' => $this->lieu_naissance,
            'nationalite' => $this->nationalite,
            'redoublant' => (bool) $this->redoublant,
            'statut' => $this->statut,
            'classe' => $this->whenLoaded('classe', fn () => $this->classe ? [
                'id' => $this->classe->id,
                'nom' => $this->classe->nom,
                'niveau' => $this->classe->relationLoaded('niveau') ? $this->classe->niveau?->name_fr : null,
            ] : null),
            'tuteurs' => TuteurResource::collection($this->whenLoaded('tuteurs')),
        ];
    }
}
