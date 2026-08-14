<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonnelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'nom_complet' => $this->nom_complet,
            'fonction_id' => $this->fonction_id,
            'fonction' => $this->fonction,
            'departement' => $this->whenLoaded('departement', fn () => [
                'id' => $this->departement?->id,
                'nom' => $this->departement?->nom,
            ]),
            'telephone' => $this->telephone,
            'email' => $this->email,
            'date_embauche' => $this->date_embauche?->format('Y-m-d'),
            'statut' => $this->statut,
            'a_un_compte' => (bool) $this->user_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
