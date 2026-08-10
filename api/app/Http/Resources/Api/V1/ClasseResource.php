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
            'professeur_principal_id' => $this->professeur_principal_id,
            'surveillant_general_id' => $this->surveillant_general_id,
            'censeur_id' => $this->censeur_id,
            'conseiller_orientation_id' => $this->conseiller_orientation_id,
            'professeur_principal' => $this->responsable('professeurPrincipal'),
            'surveillant_general' => $this->responsable('surveillantGeneral'),
            'censeur' => $this->responsable('censeur'),
            'conseiller_orientation' => $this->responsable('conseillerOrientation'),
        ];
    }

    /** @return array{id: int, nom_complet: string}|null */
    private function responsable(string $relation): mixed
    {
        return $this->whenLoaded($relation, fn () => $this->{$relation} ? [
            'id' => $this->{$relation}->id,
            'nom_complet' => $this->{$relation}->nomComplet(),
        ] : null);
    }
}
