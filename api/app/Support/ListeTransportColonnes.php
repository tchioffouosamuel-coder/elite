<?php

namespace App\Support;

/** Colonnes proposées pour une liste personnalisée du transport scolaire. */
class ListeTransportColonnes
{
    /** @var array<string, array{fr: string, en: string}> */
    public const DEFINITIONS = [
        'numero' => ['fr' => 'N°', 'en' => 'No.'],
        'nom_prenom' => ['fr' => 'Nom et prénom', 'en' => 'Full name'],
        'matricule' => ['fr' => 'Matricule', 'en' => 'Student ID'],
        'classe' => ['fr' => 'Classe', 'en' => 'Class'],
        'trajet' => ['fr' => 'Trajet', 'en' => 'Route'],
        'arret' => ['fr' => 'Arrêt', 'en' => 'Stop'],
        'lieu_dit' => ['fr' => 'Lieu-dit', 'en' => 'Area'],
        'heure_passage' => ['fr' => 'Heure passage', 'en' => 'Pickup time'],
        'option_trajet' => ['fr' => 'Sens', 'en' => 'Direction'],
        'tarif_mensuel' => ['fr' => 'Tarif mensuel', 'en' => 'Monthly fare'],
        'statut_paiement' => ['fr' => 'Statut paiement', 'en' => 'Payment status'],
        'statut' => ['fr' => 'Statut transport', 'en' => 'Transport status'],
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
