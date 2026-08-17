<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RevendicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eleve' => $this->whenLoaded('eleve', fn () => [
                'id' => $this->eleve->id,
                'nom_complet' => $this->eleve->nom_complet,
                'classe' => $this->eleve->classe?->nom,
            ]),
            'matiere' => $this->whenLoaded('classeMatiere', fn () => $this->classeMatiere?->matiere?->nom),
            'trimestre' => $this->whenLoaded('trimestre', fn () => $this->trimestre?->libelle),
            'type' => $this->type,
            'objet' => $this->objet,
            'motif' => $this->motif,
            'statut' => $this->statut,
            'decision' => $this->decision,
            'date_reception' => $this->date_reception?->format('Y-m-d'),
            'date_traitement' => $this->date_traitement?->format('Y-m-d'),
            'enregistre_par' => $this->whenLoaded('enregistrePar', fn () => $this->enregistrePar?->nom_complet),
            'traite_par' => $this->whenLoaded('traitePar', fn () => $this->traitePar?->nom_complet),
        ];
    }
}
