<?php

namespace App\Support;

use App\Models\FonctionReferentiel;
use Illuminate\Support\Collection;

/**
 * Catalogue des **attributions nominatives** : les responsabilités qu'un
 * établissement confie à un agent sur un périmètre précis — telle classe,
 * tel département — indépendamment de la fonction inscrite sur sa fiche.
 *
 * C'est la distinction que _smapp faisait déjà et qui manquait ici : la
 * fonction dit le métier (« Enseignant », « Censeur »), l'attribution dit
 * l'étendue. Les deux se cumulent, et les combinaisons attendues sont réelles :
 *
 * - un enseignant désigné surveillant général d'une classe garde ses
 *   prérogatives d'enseignant là où il enseigne et gagne celles de la
 *   discipline sur les classes qu'il surveille ;
 * - un agent dont la fonction *est* surveillant général n'enseigne pas : il ne
 *   tient que la discipline, et seulement sur les classes qui lui sont
 *   assignées ;
 * - le professeur principal est enseignant avant tout : il ajoute la conduite
 *   de la classe dont il a la charge à ses prérogatives ordinaires ;
 * - le censeur suit la même logique que le surveillant général, sur le versant
 *   pédagogique et sur son groupe de classes ;
 * - le chef de département pilote les matières de son département.
 *
 * Un privilège conféré ici ne vaut **que** sur le périmètre de l'attribution
 * (cf. {@see Perimetre::peutSurClasse()}) : porter `discipline.manage` parce
 * qu'on surveille six classes n'ouvre pas la discipline de l'établissement.
 */
class Attributions
{
    public const PROFESSEUR_PRINCIPAL = 'professeur_principal';

    public const SURVEILLANT_GENERAL = 'surveillant_general';

    public const CENSEUR = 'censeur';

    public const CONSEILLER_ORIENTATION = 'conseiller_orientation';

    public const CHEF_DEPARTEMENT = 'chef_departement';

    /**
     * code => [
     *   colonne     : colonne porteuse (sur `classes`, ou `departements` pour
     *                 le chef de département),
     *   portee      : 'classe' | 'departement',
     *   libelles    : [fr, en],
     *   roles       : rôles (cf. FonctionRoles) dont la fonction rend un agent
     *                 éligible à l'attribution,
     *   permissions : privilèges conférés sur le périmètre attribué,
     * ]
     */
    private const CATALOGUE = [
        /*
         * Professeur principal : enseignant de la classe, plus la conduite de
         * celle-ci. _smapp lui ouvrait « Ma classe » — élèves, enseignants et
         * coefficients de la classe, emploi du temps, statistiques
         * pédagogiques, bilan disciplinaire et sanctions. Il consulte la
         * discipline sans la tenir : c'est l'affaire du surveillant général.
         */
        self::PROFESSEUR_PRINCIPAL => [
            'colonne' => 'professeur_principal_id',
            'portee' => 'classe',
            'libelles' => ['Professeur principal', 'Form master'],
            'roles' => ['enseignant'],
            'permissions' => [
                'classes.view',
                'eleves.view',
                'pedagogie.view',
                'pedagogie.manage',
                'notes.view',
                'discipline.view',
                'bulletins.view',
                'emploi_du_temps.view',
                'revendications.view',
                'annonces.view',
                'dashboard.view',
            ],
        ],
        /*
         * Surveillant général : la discipline, rien que la discipline. Il fait
         * et corrige l'appel, enregistre absences et sanctions, consulte les
         * bulletins pour les besoins du conseil — mais ne saisit pas de notes,
         * qu'il soit enseignant par ailleurs ou non.
         */
        self::SURVEILLANT_GENERAL => [
            'colonne' => 'surveillant_general_id',
            'portee' => 'classe',
            'libelles' => ['Surveillant général', 'Discipline master'],
            'roles' => ['enseignant', 'surveillant_general'],
            'permissions' => [
                'classes.view',
                'eleves.view',
                'discipline.view',
                'discipline.manage',
                'appel.manage',
                'bulletins.view',
                'emploi_du_temps.view',
                'revendications.view',
                'annonces.view',
                'dashboard.view',
            ],
        ],
        /*
         * Censeur : le pédagogique sur son groupe de classes — affectations,
         * notes, emploi du temps, publication des bulletins. Il voit la
         * discipline sans la tenir, symétrique du surveillant général.
         */
        self::CENSEUR => [
            'colonne' => 'censeur_id',
            'portee' => 'classe',
            'libelles' => ['Censeur', 'Vice-principal'],
            'roles' => ['enseignant', 'censeur_sg'],
            'permissions' => [
                'classes.view',
                'eleves.view',
                'pedagogie.view',
                'pedagogie.manage',
                'notes.view',
                'notes.create',
                'bulletins.view',
                'bulletins.publish',
                'emploi_du_temps.view',
                'emploi_du_temps.manage',
                'discipline.view',
                'revendications.view',
                'revendications.manage',
                'annonces.view',
                'dashboard.view',
            ],
        ],
        /*
         * Conseiller d'orientation : il suit les élèves de ses classes, leurs
         * résultats et leur assiduité, sans rien y modifier.
         */
        self::CONSEILLER_ORIENTATION => [
            'colonne' => 'conseiller_orientation_id',
            'portee' => 'classe',
            'libelles' => ["Conseiller d'orientation", 'Guidance counsellor'],
            'roles' => ['enseignant', 'surveillant_general'],
            'permissions' => [
                'classes.view',
                'eleves.view',
                'discipline.view',
                'bulletins.view',
                'revendications.view',
                'annonces.view',
                'dashboard.view',
            ],
        ],
        /*
         * Chef de département : les matières de son département et le suivi
         * pédagogique des enseignants qui y sont rattachés — l'écran « Mon
         * département » de _smapp.
         */
        self::CHEF_DEPARTEMENT => [
            'colonne' => 'head_personnel_id',
            'portee' => 'departement',
            'libelles' => ['Chef de département', 'Head of department'],
            'roles' => ['enseignant'],
            'permissions' => [
                'personnel.view',
                'classes.view',
                'pedagogie.view',
                'pedagogie.manage',
                'notes.view',
                'bulletins.view',
                'annonces.view',
                'dashboard.view',
            ],
        ],
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::CATALOGUE);
    }

    public static function existe(string $code): bool
    {
        return isset(self::CATALOGUE[$code]);
    }

    /** Attributions portées par une classe (toutes sauf le chef de département). */
    public static function surClasse(): array
    {
        return array_keys(array_filter(self::CATALOGUE, fn (array $a) => $a['portee'] === 'classe'));
    }

    /** Colonne qui porte l'attribution sur `classes` ou `departements`. */
    public static function colonne(string $code): string
    {
        return self::CATALOGUE[$code]['colonne'];
    }

    public static function portee(string $code): string
    {
        return self::CATALOGUE[$code]['portee'];
    }

    /**
     * Privilèges conférés par l'attribution, valables sur son seul périmètre.
     *
     * @return list<string>
     */
    public static function permissions(string $code): array
    {
        return self::CATALOGUE[$code]['permissions'] ?? [];
    }

    public static function libelle(string $code, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        [$fr, $en] = self::CATALOGUE[$code]['libelles'] ?? [$code, $code];

        return $locale === 'en' ? $en : $fr;
    }

    /**
     * Rôles (au sens de `FonctionRoles`) dont la fonction rend éligible à
     * l'attribution. Un enseignant peut être désigné surveillant général d'une
     * classe ; un économe, non.
     *
     * @return list<string>
     */
    public static function rolesEligibles(string $code): array
    {
        return self::CATALOGUE[$code]['roles'] ?? [];
    }

    /**
     * Une fonction rend-elle éligible à cette attribution ? Une fonction hors
     * référentiel (libellé inconnu, donc sans rôle) n'ouvre rien : l'attribuer
     * reviendrait à accorder des privilèges sur la foi d'un texte libre.
     */
    public static function fonctionEligible(string $code, ?string $labelFonction): bool
    {
        $role = FonctionRoles::role($labelFonction);

        return $role !== null && in_array($role, self::rolesEligibles($code), true);
    }

    /**
     * Fonctions du référentiel qui rendent éligible à l'attribution, pour
     * l'établissement donné. Le formulaire des responsables de classe s'en
     * sert pour ne proposer que des candidats plausibles : au surveillant
     * général, les enseignants et les surveillants généraux ; au censeur, les
     * enseignants et les censeurs ; au professeur principal, les enseignants
     * seulement.
     *
     * @param  int|array<int>  $schoolId
     * @return list<int>
     */
    public static function fonctionsEligibles(string $code, int|array $schoolId): array
    {
        return FonctionReferentiel::forSchool($schoolId)
            ->get(['id', 'label_fr'])
            ->filter(fn (FonctionReferentiel $fonction) => self::fonctionEligible($code, $fonction->label_fr))
            ->pluck('id')
            ->all();
    }

    /**
     * Catalogue mis en forme pour l'interface (écran des responsables de
     * classe, fiche d'un agent).
     *
     * @return Collection<int, array{code: string, libelle: string, portee: string, permissions: list<string>}>
     */
    public static function pourInterface(?string $locale = null): Collection
    {
        return collect(self::CATALOGUE)->map(fn (array $attribution, string $code) => [
            'code' => $code,
            'libelle' => self::libelle($code, $locale),
            'portee' => $attribution['portee'],
            'permissions' => $attribution['permissions'],
        ])->values();
    }
}
