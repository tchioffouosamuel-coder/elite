<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EleveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'nom_complet' => $this->nom_complet,
            'sexe' => $this->sexe,
            'date_naissance' => $this->date_naissance?->format('Y-m-d'),
            'age' => $this->age,
            'lieu_naissance' => $this->lieu_naissance,
            'nationalite' => $this->nationalite,
            'photo_url' => $this->photo_path ? asset('storage/'.$this->photo_path) : null,
            'groupe_sanguin' => $this->groupe_sanguin,
            'situation_sanitaire' => $this->situation_sanitaire,
            'aptitude' => $this->aptitude,
            'allergies' => $this->allergies,
            'redoublant' => (bool) $this->redoublant,
            'statut' => $this->statut,
            'school_id' => $this->school_id,
            'school' => $this->whenLoaded('school', fn () => $this->school ? [
                'id' => $this->school->id,
                'name' => $this->school->name,
                'code' => $this->school->code,
                'type' => $this->school->type,
            ] : null),
            'classe' => $this->whenLoaded('classe', fn () => $this->classe ? [
                'id' => $this->classe->id,
                'nom' => $this->classe->nom,
                'niveau' => $this->classe->relationLoaded('niveau') ? $this->classe->niveau?->name_fr : null,
            ] : null),
            'tuteurs' => TuteurResource::collection($this->whenLoaded('tuteurs')),
        ];
    }
}
