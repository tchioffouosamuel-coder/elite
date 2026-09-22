<?php

namespace App\Support;

/** Colonnes proposées pour une liste personnalisée des enseignants. */
class ListeEnseignantColonnes
{
    /** @var array<string, array{fr: string, en: string}> */
    public const DEFINITIONS = [
        'numero' => ['fr' => 'N°', 'en' => 'No.'],
        'matricule' => ['fr' => 'Matricule', 'en' => 'Staff ID'],
        'nom_prenom' => ['fr' => 'Nom et prénom', 'en' => 'Full name'],
        'fonction' => ['fr' => 'Fonction', 'en' => 'Position'],
        'departement' => ['fr' => 'Département', 'en' => 'Department'],
        'telephone' => ['fr' => 'Téléphone', 'en' => 'Phone'],
        'telephone_2' => ['fr' => 'Téléphone 2', 'en' => 'Phone 2'],
        'email' => ['fr' => 'E-mail', 'en' => 'Email'],
        'sexe' => ['fr' => 'Sexe', 'en' => 'Sex'],
        'date_naissance' => ['fr' => 'Date de naissance', 'en' => 'Date of birth'],
        'anciennete' => ['fr' => 'Ancienneté', 'en' => 'Seniority'],
        'type_contrat' => ['fr' => 'Type contrat', 'en' => 'Contract type'],
        'statut_contrat' => ['fr' => 'Statut contrat', 'en' => 'Contract status'],
        'grade_minedub' => ['fr' => 'Grade', 'en' => 'Grade'],
        'categorie_echelon' => ['fr' => 'Catégorie/Échelon', 'en' => 'Category/Step'],
        'diplome_professionnel' => ['fr' => 'Diplôme professionnel', 'en' => 'Professional diploma'],
        'diplome_academique' => ['fr' => 'Diplôme académique', 'en' => 'Academic diploma'],
        'residence' => ['fr' => 'Résidence', 'en' => 'Residence'],
        'affectation' => ['fr' => 'Affectation', 'en' => 'Duty post'],
        'compte' => ['fr' => 'Compte', 'en' => 'Account'],
        'statut' => ['fr' => 'Statut', 'en' => 'Status'],
        'ecole' => ['fr' => 'École', 'en' => 'School'],
    ];

    /** @return list<string> */
    public static function clesValides(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public static function libelle(string $cle, bool $anglais = false): string
    {
        return self::DEFINITIONS[$cle][$anglais ? 'en' : 'fr'] ?? $cle;
    }

    public static function libelles(string $cle): array
    {
        return [self::libelle($cle), self::libelle($cle, true)];
    }
}
