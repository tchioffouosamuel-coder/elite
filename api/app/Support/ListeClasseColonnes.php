<?php

namespace App\Support;

/**
 * Colonnes proposées par la liste personnalisée de classe, et leurs
 * intitulés bilingues — source unique pour les trois formats d'export (PDF,
 * Word, Excel) afin qu'ils restent alignés si la liste évolue.
 */
class ListeClasseColonnes
{
    /** @var array<string, array{fr: string, en: string}> */
    public const DEFINITIONS = [
        'numero' => ['fr' => 'N°', 'en' => 'No.'],
        'nom_prenom' => ['fr' => 'Nom et prénom', 'en' => 'Full name'],
        'date_naissance' => ['fr' => 'Date de naissance', 'en' => 'Date of birth'],
        'lieu_naissance' => ['fr' => 'Lieu de naissance', 'en' => 'Place of birth'],
        'sexe' => ['fr' => 'Sexe', 'en' => 'Sex'],
        'age' => ['fr' => 'Âge', 'en' => 'Age'],
        'statut_solvabilite' => ['fr' => 'Statut solvabilité', 'en' => 'Solvency status'],
        'reste_scolarite_a_payer' => ['fr' => 'Reste scolarité à payer', 'en' => 'Tuition balance due'],
        'situation_transport' => ['fr' => 'Situation transport', 'en' => 'Transport status'],
        'dette_anterieure' => ['fr' => 'Dette antérieure', 'en' => 'Previous debt'],
        'moyenne' => ['fr' => 'Moyenne', 'en' => 'Average'],
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

    /** Intitulé bilingue affiché sur deux lignes (FR au-dessus, EN en italique). */
    public static function libelles(string $cle): array
    {
        return [self::libelle($cle), self::libelle($cle, true)];
    }
}
