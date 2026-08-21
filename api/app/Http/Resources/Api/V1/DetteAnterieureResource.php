<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetteAnterieureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eleve' => [
                'id' => $this->eleve?->id,
                'nom_complet' => $this->eleve?->nom_complet,
            ],
            'montant' => $this->montant,
            'motif' => $this->motif,
            'imputee' => $this->imputee_dossier_id !== null,
            'accorde_par' => $this->accordePar?->name,
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
