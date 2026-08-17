<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Correspondance entre le libellé d'une fonction du référentiel et le rôle
 * dont elle reprend les privilèges par défaut (cf. FonctionPermissionSeeder,
 * seule autre consommatrice de cette table — elle y réfère plutôt que d'en
 * garder sa propre copie).
 *
 * Sert aussi à repérer les fonctions d'enseignement (cf. User::estEnseignant),
 * pour distinguer un enseignant d'un censeur ou d'un économe qui partagent
 * pourtant certains privilèges (ex : `appel.manage`) sans exercer le même
 * métier.
 */
class FonctionRoles
{
    /**
     * Libellé de fonction (normalisé, sans accent ni casse) => rôle dont elle
     * reprend les privilèges. `null` = aucun accès à l'application : la
     * fonction existe pour le fichier du personnel, pas pour ouvrir un compte.
     */
    public const CORRESPONDANCES = [
        'enseignant' => 'enseignant',
        'principal' => 'admin_etablissement',
        'directeur' => 'admin_etablissement',
        'censeur' => 'censeur_sg',
        'surveillant general' => 'surveillant_general',
        'conseiller d orientation' => 'surveillant_general',
        'econome' => 'econome',
        'secretaire' => 'econome',
        'documentaliste' => null,
        'infirmier' => null,
        'gardien' => null,
        'agent d entretien' => null,
        'chauffeur' => null,
    ];

    public static function role(?string $labelFr): ?string
    {
        if ($labelFr === null || trim($labelFr) === '') {
            return null;
        }

        return self::CORRESPONDANCES[self::normaliser($labelFr)] ?? null;
    }

    /** « Surveillant Général » et « surveillant general » désignent la même fonction. */
    public static function normaliser(string $label): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($label))) ?? '');
    }
}
