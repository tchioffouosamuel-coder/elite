<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NiveauScolaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'libelle' => $this->libelle,
            'ordre' => $this->ordre,
            'animateur_personnel_id' => $this->animateur_personnel_id,
            'animateur' => $this->whenLoaded('animateur', fn () => $this->animateur ? [
                'id' => $this->animateur->id,
                'nom_complet' => $this->animateur->nom_complet,
            ] : null),
            'nb_classes' => $this->when(isset($this->classes_count), $this->classes_count),
        ];
    }
}
