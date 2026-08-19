<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonnelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'nom_complet' => $this->nom_complet,
            'fonction_id' => $this->fonction_id,
            'fonction' => $this->fonction,
            'affectation' => $this->affectation,
            'departement' => $this->whenLoaded('departement', fn () => [
                'id' => $this->departement?->id,
                'nom' => $this->departement?->nom,
            ]),
            'school_id' => $this->school_id,
            'school' => $this->whenLoaded('school', fn () => $this->school ? [
                'id' => $this->school->id,
                'name' => $this->school->name,
                'code' => $this->school->code,
                'type' => $this->school->type,
            ] : null),
            'civilite' => $this->civilite,
            'sexe' => $this->sexe,
            'date_naissance' => $this->date_naissance?->format('Y-m-d'),
            'numero_cni' => $this->numero_cni,
            'numero_cnps' => $this->numero_cnps,
            'departement_origine' => $this->departement_origine,
            'residence' => $this->residence,
            'telephone' => $this->telephone,
            'telephone_2' => $this->telephone_2,
            'situation_matrimoniale' => $this->situation_matrimoniale,
            'nombre_enfants' => $this->nombre_enfants,
            'diplome_professionnel' => $this->diplome_professionnel,
            'diplome_academique' => $this->diplome_academique,
            'email' => $this->email,
            'date_embauche' => $this->date_embauche?->format('Y-m-d'),
            'date_fin' => $this->date_fin?->format('Y-m-d'),
            'date_retraite' => $this->date_retraite_calculee,
            'statut' => $this->statut,
            'a_un_compte' => (bool) $this->user_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
