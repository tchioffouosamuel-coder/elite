<?php

namespace App\Http\Requests\Api\V1\Concerns;

/**
 * Règles du dossier administratif d'un agent, partagées par la création et la
 * mise à jour — seules l'identité et la fonction diffèrent entre les deux
 * (obligatoires à la création, `sometimes` ensuite).
 */
trait ReglesDossierPersonnel
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function reglesDossier(): array
    {
        return [
            'departement_id' => ['nullable', $this->scopedExists('departements')],
            'affectation' => ['nullable', 'string', 'max:150'],
            'matricule' => ['nullable', 'string', 'max:50'],
            'civilite' => ['nullable', 'string', 'max:20'],
            'sexe' => ['nullable', 'in:M,F'],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'numero_cni' => ['nullable', 'string', 'max:50'],
            'numero_cnps' => ['nullable', 'string', 'max:50'],
            'departement_origine' => ['nullable', 'string', 'max:100'],
            'residence' => ['nullable', 'string', 'max:150'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'telephone_2' => ['nullable', 'string', 'max:30'],
            'situation_matrimoniale' => ['nullable', 'in:celibataire,marie,divorce,veuf'],
            'nombre_enfants' => ['nullable', 'integer', 'min:0', 'max:30'],
            'diplome_professionnel' => ['nullable', 'string', 'max:100'],
            'diplome_academique' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'date_embauche' => ['nullable', 'date'],
            // Une fin de contrat antérieure à l'embauche relève de la faute de
            // saisie ; la laisser passer ferait basculer l'agent en « sorti ».
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_embauche'],
            'date_retraite' => ['nullable', 'date'],
            'statut' => ['nullable', 'in:actif,ex_employe'],
        ];
    }
}
