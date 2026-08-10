<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrimestreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'annee_scolaire_id' => $this->annee_scolaire_id,
            'libelle' => $this->libelle,
            'ordre' => $this->ordre,
            'date_debut' => $this->date_debut?->format('Y-m-d'),
            'date_fin' => $this->date_fin?->format('Y-m-d'),
            'is_active' => (bool) $this->is_active,
            'sequences' => SequenceResource::collection($this->whenLoaded('sequences')),
        ];
    }
}
