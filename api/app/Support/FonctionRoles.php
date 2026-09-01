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
        // Le collège Elites-tech est dirigé par un « Principal », l'école
        // maternelle/primaire par une « Directrice » : deux gouvernances
        // distinctes, donc deux rôles distincts (cf. School::estSecondaire).
        'principal' => 'admin_college',
        'directeur' => 'admin_ecole',
        'censeur' => 'censeur_sg',
        'vice principal' => 'censeur_sg',
        'surveillant general' => 'surveillant_general',
        'conseiller d orientation' => 'surveillant_general',
        'econome' => 'econome',
        'comptable' => 'econome',
        'secretaire' => 'econome',
        'secretaire comptable' => 'econome',
        'vendeur' => 'vendeur',
        'vendeuse' => 'vendeur',
        'caissier' => 'vendeur',
        'caissiere' => 'vendeur',
        'documentaliste' => null,
        'infirmier' => 'infirmier',
        'infirmiere' => 'infirmier',
        'gardien' => 'agent_securite',
        'agent de securite' => 'agent_securite',
        'agent d entretien' => 'agent_entretien',
        'agent de proprete' => 'agent_entretien',
        'chauffeur' => 'chauffeur',
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
