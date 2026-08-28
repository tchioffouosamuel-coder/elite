<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClasseMatiereResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matiere' => [
                'id' => $this->matiere->id,
                'nom' => $this->matiere->nom,
                'nom_en' => $this->matiere->nom_en,
                'abbreviation' => $this->matiere->abbreviation,
            ],
            'enseignant' => $this->enseignant ? [
                'id' => $this->enseignant->id,
                'nom_complet' => $this->enseignant->nom_complet,
            ] : null,
            'coefficient' => (float) $this->coefficient,
            'quota_horaire' => $this->quota_horaire,
            'groupe' => $this->groupe,
            'competences' => $this->competences,
            'statut' => $this->statut,
        ];
    }
}
