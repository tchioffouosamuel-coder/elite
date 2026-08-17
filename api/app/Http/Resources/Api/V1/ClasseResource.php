<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClasseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'sigle' => $this->sigle,
            'niveau_classe' => $this->niveau_classe,
            'filiere' => $this->filiere,
            'code_examen' => $this->code_examen,
            'capacite' => $this->capacite,
            'qr_token' => $this->qr_token,
            'niveau_id' => $this->niveau_id,
            'niveau_scolaire_id' => $this->niveau_scolaire_id,
            'sous_systeme_id' => $this->sous_systeme_id,
            'annee_scolaire_id' => $this->annee_scolaire_id,
            'effectif' => $this->when(isset($this->eleves_count), $this->eleves_count),
            'niveau' => $this->whenLoaded('niveau', fn() => [
                'id' => $this->niveau?->id,
                'code' => $this->niveau?->code,
                'name_fr' => $this->niveau?->name_fr,
            ]),
            'niveau_scolaire' => $this->whenLoaded('niveauScolaire', fn() => $this->niveauScolaire ? [
                'id' => $this->niveauScolaire->id,
                'code' => $this->niveauScolaire->code,
                'libelle' => $this->niveauScolaire->libelle,
            ] : null),
            'sous_systeme' => $this->whenLoaded('sousSysteme', fn() => $this->sousSysteme ? [
                'id' => $this->sousSysteme->id,
                'code' => $this->sousSysteme->code,
                'nom' => $this->sousSysteme->nom,
            ] : null),
            'professeur_principal_id' => $this->professeur_principal_id,
            'titulaire_id' => $this->titulaire_id,
            'titulaire' => $this->responsable('titulaire'),
            'surveillant_general_id' => $this->surveillant_general_id,
            'censeur_id' => $this->censeur_id,
            'conseiller_orientation_id' => $this->conseiller_orientation_id,
            'professeur_principal' => $this->responsable('professeurPrincipal'),
            'surveillant_general' => $this->responsable('surveillantGeneral'),
            'censeur' => $this->responsable('censeur'),
            'conseiller_orientation' => $this->responsable('conseillerOrientation'),
        ];
    }

    /** @return array{id: int, nom_complet: string}|null */
    private function responsable(string $relation): mixed
    {
        return $this->whenLoaded($relation, fn() => $this->{$relation} ? [
            'id' => $this->{$relation}->id,
            'nom_complet' => $this->{$relation}->nom_complet,
        ] : null);
    }
}
