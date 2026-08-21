<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MoratoireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eleve' => [
                'id' => $this->eleve?->id,
                'nom_complet' => $this->eleve?->nom_complet,
                'matricule' => $this->eleve?->matricule,
            ],
            'date_delivrance' => $this->date_delivrance?->format('Y-m-d'),
            'date_expiration' => $this->date_expiration?->format('Y-m-d'),
            'motif' => $this->motif,
            'valide' => $this->valide,
            'accorde_par' => $this->accordePar?->name,
        ];
    }
}
