<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnonceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'contenu' => $this->contenu,
            'publiee_le' => $this->publiee_le?->toIso8601String(),
            'cible_type' => $this->cible_type,
            'publie_par' => $this->whenLoaded('publiePar', fn () => $this->publiePar ? [
                'id' => $this->publiePar->id,
                'nom_complet' => $this->publiePar->nom_complet,
            ] : null),
            'school' => $this->whenLoaded('school', fn () => $this->school ? [
                'id' => $this->school->id,
                'name' => $this->school->name,
                'code' => $this->school->code,
                'type' => $this->school->type,
            ] : null),
        ];
    }
}
