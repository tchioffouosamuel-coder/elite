<?php

namespace App\Http\Requests\Api\V1\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

/**
 * Règles du dossier administratif d'un agent, partagées par la création et la
 * mise à jour — seules l'identité, la fonction et les informations de famille
 * diffèrent entre les deux (obligatoires à la création, `sometimes` ensuite).
 */
trait ReglesDossierPersonnel
{
    protected function prepareForValidation(): void
    {
        $normalisees = [];

        if ($this->exists('civilite')) {
            $normalisees['sexe'] = match ($this->input('civilite')) {
                'M.', 'Mr' => 'M',
                'Mme', 'Mlle', 'Mrs', 'Miss' => 'F',
                default => null,
            };
        }

        if ($this->exists('type_contrat')) {
            $normalisees['statut_contrat'] = match ($this->input('type_contrat')) {
                'CDI' => 'permanent',
                'CDD' => 'vacataire',
                default => null,
            };
        }

        if ($this->exists('date_naissance')) {
            $dateNaissance = $this->input('date_naissance');
            $normalisees['date_retraite'] = null;

            if (is_string($dateNaissance) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateNaissance)) {
                try {
                    $normalisees['date_retraite'] = CarbonImmutable::createFromFormat('!Y-m-d', $dateNaissance)
                        ->addYearsNoOverflow(60)
                        ->format('Y-m-d');
                } catch (\Throwable) {
                    // La règle de validation de date produira le message adapté.
                }
            }
        }

        $this->merge($normalisees);
    }

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
            'numero_permis' => ['nullable', 'string', 'max:50'],
            'banque_id' => ['nullable', $this->scopedExists('banques')],
            'numero_compte' => ['nullable', 'string', 'max:100'],
            'methode_validation_seance' => ['nullable', 'in:qr,code,libre'],
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
            'pere_nom_complet' => ['nullable', 'string', 'max:200'],
            'pere_statut' => ['nullable', 'in:vivant,decede'],
            'pere_telephone' => ['nullable', 'string', 'max:30'],
            'mere_nom_complet' => ['nullable', 'string', 'max:200'],
            'mere_statut' => ['nullable', 'in:vivant,decede'],
            'mere_telephone' => ['nullable', 'string', 'max:30'],
            'enfants' => ['nullable', 'array', 'max:20'],
            'enfants.*.nom_complet' => ['nullable', 'string', 'max:200'],
            'enfants.*.sexe' => ['nullable', 'in:M,F'],
            'enfants.*.date_naissance' => ['nullable', 'date', 'before:today'],
            'statut' => ['nullable', 'in:actif,ex_employe'],
            'type_contrat' => ['nullable', 'in:CDI,CDD'],
            'statut_contrat' => ['nullable', 'in:essai,permanent,vacataire'],
            'categorie_echelon' => ['nullable', 'string', 'max:20'],
            'grade_minedub' => ['nullable', 'string', 'max:50'],
            'absent_depuis' => ['nullable', 'date'],
            'motif_absence' => ['nullable', 'string', 'max:255'],
            'dossier_disciplinaire' => ['nullable', 'boolean'],
            'date_deces' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validerParent($validator, 'pere');
            $this->validerParent($validator, 'mere');
            $this->validerEnfants($validator);
        });
    }

    private function validerParent(Validator $validator, string $prefixe): void
    {
        $nom = trim((string) $this->input($prefixe . '_nom_complet', ''));
        $statut = $this->input($prefixe . '_statut');
        $telephone = trim((string) $this->input($prefixe . '_telephone', ''));

        if ($nom === '' && $statut === null && $telephone === '') {
            return;
        }

        if ($nom === '') {
            $validator->errors()->add($prefixe . '_nom_complet', 'Le nom du parent est obligatoire.');
        }

        if ($statut === null || $statut === '') {
            $validator->errors()->add($prefixe . '_statut', 'Le statut du parent est obligatoire.');
        }

        if (($statut === 'vivant') && $telephone === '') {
            $validator->errors()->add($prefixe . '_telephone', 'Le téléphone du parent vivant est obligatoire.');
        }
    }

    private function validerEnfants(Validator $validator): void
    {
        $enfants = $this->input('enfants', []);

        if (! is_array($enfants)) {
            return;
        }

        foreach ($enfants as $index => $enfant) {
            if (! is_array($enfant)) {
                continue;
            }

            $nom = trim((string) ($enfant['nom_complet'] ?? ''));
            $sexe = $enfant['sexe'] ?? null;
            $dateNaissance = trim((string) ($enfant['date_naissance'] ?? ''));

            if ($nom === '' && ($sexe === null || $sexe === '') && $dateNaissance === '') {
                continue;
            }

            if ($nom === '') {
                $validator->errors()->add("enfants.$index.nom_complet", "Le nom de l'enfant est obligatoire.");
            }

            if ($sexe !== 'M' && $sexe !== 'F') {
                $validator->errors()->add("enfants.$index.sexe", "Le sexe de l'enfant est obligatoire.");
            }

            if ($dateNaissance === '') {
                $validator->errors()->add("enfants.$index.date_naissance", "La date de naissance de l'enfant est obligatoire.");
            }
        }
    }
}
