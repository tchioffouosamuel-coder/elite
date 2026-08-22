<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatiereResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'nom_en' => $this->nom_en,
            'abbreviation' => $this->abbreviation,
            'statut' => $this->statut,
            'competence_id' => $this->competence_id,
            'competence' => $this->whenLoaded('competence', fn () => $this->competence ? [
                'id' => $this->competence->id,
                'label_fr' => $this->competence->label_fr,
                'label_en' => $this->competence->label_en,
                'notation' => $this->competence->notation,
            ] : null),
            'classes_count' => $this->whenCounted('classeMatieres'),
            'departement' => $this->whenLoaded('departement', fn () => $this->departement ? [
                'id' => $this->departement->id,
                'nom' => $this->departement->nom,
            ] : null),
            'school_id' => $this->school_id,
            'school' => $this->whenLoaded('school', fn () => $this->school ? [
                'id' => $this->school->id,
                'name' => $this->school->name,
                'code' => $this->school->code,
                'type' => $this->school->type,
            ] : null),
        ];
    }
}
