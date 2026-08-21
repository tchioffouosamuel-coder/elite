<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisiteInfirmerieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eleve' => $this->whenLoaded('eleve', fn () => [
                'id' => $this->eleve->id,
                'nom_complet' => $this->eleve->nom_complet,
            ]),
            'classe' => $this->whenLoaded('classe', fn () => $this->classe ? [
                'id' => $this->classe->id,
                'nom' => $this->classe->nom,
            ] : null),
            'school' => $this->when($this->relationLoaded('eleve') && $this->eleve?->relationLoaded('school'), fn () => $this->eleve->school ? [
                'id' => $this->eleve->school->id,
                'name' => $this->eleve->school->name,
                'code' => $this->eleve->school->code,
                'type' => $this->eleve->school->type,
            ] : null),
            'date_visite' => $this->date_visite?->format('Y-m-d\TH:i'),
            'raison' => $this->raison,
            'malaises' => $this->whenLoaded('malaises', fn () => $this->malaises->map(fn ($m) => [
                'id' => $m->id,
                'label_fr' => $m->label_fr,
                'label_en' => $m->label_en,
            ])),
            'soins_prodiges' => $this->soins_prodiges,
            'type_traitement' => $this->type_traitement,
            'structure_externe' => $this->structure_externe,
            'materiels' => $this->whenLoaded('materiels', fn () => $this->materiels->map(fn ($m) => [
                'id' => $m->id,
                'inventaire_article_id' => $m->inventaire_article_id,
                'nom' => $m->nom,
                'quantite' => $m->quantite,
                'cout_unitaire' => $m->cout_unitaire,
                'cout' => $m->cout,
                'article_disponible' => $m->relationLoaded('article') ? $m->article !== null : null,
            ])),
            'autre_materiel' => $this->autre_materiel,
            'cout_autre_materiel' => $this->cout_autre_materiel,
            'cout_soins' => $this->cout_soins,
            'cout_materiels' => $this->cout_materiels,
            'cout_total' => $this->cout_total,
            'observations' => $this->observations,
            'enregistre_par' => $this->enregistrePar?->nom_complet,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
