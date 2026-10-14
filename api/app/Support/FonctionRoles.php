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
 * pourtant certains privilèges (ex : `appel.saisir`) sans exercer le même
 * métier.
 */
class FonctionRoles
{
    /**
     * Libellé de fonction (normalisé, sans accent ni casse) => rôle dont elle
     * reprend les privilèges. `null` = aucun accès à l'application : la
     * fonction existe pour le fichier du personnel, pas pour ouvrir un compte.
     *
     * Les libellés anglais du référentiel (`label_en` : « Teacher »,
     * « Bursar »…) y figurent à côté des français, ainsi que les variantes
     * d'usage (féminin, « professeur », « instituteur ») : un établissement
     * bilingue nomme ses fonctions dans l'une ou l'autre langue, et une
     * fonction non reconnue arrive sans privilège — donc un enseignant sans
     * accès à la saisie des notes.
     */
    public const CORRESPONDANCES = [
        'enseignant' => 'enseignant',
        'enseignante' => 'enseignant',
        'teacher' => 'enseignant',
        'professeur' => 'enseignant',
        'professeure' => 'enseignant',
        'instituteur' => 'enseignant',
        'institutrice' => 'enseignant',
        // Le collège Elites-tech est dirigé par un « Principal », l'école
        // maternelle/primaire par une « Directrice » : deux gouvernances
        // distinctes, donc deux rôles distincts (cf. School::estSecondaire).
        'principal' => 'admin_college',
        'directeur' => 'admin_ecole',
        'directrice' => 'admin_ecole',
        'headmaster' => 'admin_ecole',
        'headmistress' => 'admin_ecole',
        'censeur' => 'censeur_sg',
        'vice principal' => 'censeur_sg',
        'surveillant general' => 'surveillant_general',
        'surveillante generale' => 'surveillant_general',
        'discipline master' => 'surveillant_general',
        'conseiller d orientation' => 'surveillant_general',
        'guidance counsellor' => 'surveillant_general',
        'guidance counselor' => 'surveillant_general',
        'econome' => 'econome',
        'bursar' => 'econome',
        'comptable' => 'econome',
        'accountant' => 'econome',
        'secretaire' => 'econome',
        'secretary' => 'econome',
        'secretaire comptable' => 'econome',
        'vendeur' => 'vendeur',
        'vendeuse' => 'vendeur',
        'caissier' => 'vendeur',
        'caissiere' => 'vendeur',
        'cashier' => 'vendeur',
        'documentaliste' => null,
        'librarian' => null,
        'infirmier' => 'infirmier',
        'infirmiere' => 'infirmier',
        'nurse' => 'infirmier',
        'gardien' => 'agent_securite',
        'agent de securite' => 'agent_securite',
        'security guard' => 'agent_securite',
        'agent d entretien' => 'agent_entretien',
        'agent de proprete' => 'agent_entretien',
        'cleaner' => 'agent_entretien',
        'chauffeur' => 'chauffeur',
        'driver' => 'chauffeur',
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
