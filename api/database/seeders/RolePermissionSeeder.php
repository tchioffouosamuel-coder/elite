<?php

namespace Database\Seeders;

use App\Support\CataloguePermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /*
     * Domaines entiers, tels qu'un rôle les reçoit par défaut : toutes les
     * actions de chaque entité du domaine. Le découpage par action sert au
     * super administrateur, qui retire ensuite une case depuis l'écran des
     * permissions (« ce censeur ne supprime pas de sanction ») ; les rôles,
     * eux, partent d'un domaine complet.
     */
    private const ETABLISSEMENT = ['ecoles.*', 'parametres.*', 'annees_scolaires.*', 'trimestres.*'];

    private const PERSONNEL = ['personnel.*', 'departements.*', 'fonctions.*', 'banques.*', 'regles_seance.*'];

    private const CLASSES = ['classes.*', 'sous_systemes.*'];

    // Pas de `eleves.*` : il emporterait `eleves.situation`, la vue en lecture
    // seule réservée à l'enseignant.
    private const ELEVES = [
        'eleves.create', 'eleves.update', 'eleves.delete', 'eleves.import',
        'eleves.transferer', 'eleves.fusionner', 'eleves.comptes',
        'matricules_nationaux.*', 'tuteurs.*', 'preinscriptions.*',
        'modifications_eleves.*', 'justifications.*', 'observations.*',
    ];

    private const PEDAGOGIE = [
        'matieres.*', 'affectations.*', 'tronc_commun.*', 'calendrier_scolaire.*',
        'competences.*', 'appreciations.*', 'niveaux_scolaires.*',
        'progression.*', 'evaluations.*',
    ];

    private const DISCIPLINE = ['absences.*', 'sanctions.*'];

    private const INFIRMERIE = ['infirmerie.*', 'malaises.*'];

    // Flotte, trajets et arrêts — sans `bus.souscrire`, accordé à part.
    private const FLOTTE_BUS = ['bus_vehicules.*', 'bus_trajets.*', 'bus_arrets.*'];

    private const INVENTAIRE = ['inventaire.*', 'demandes_articles.*'];

    private const INFRASTRUCTURES = ['infrastructures.*', 'equipements.*'];

    private const RAPPORT_RENTREE = ['rapport_rentree.*', 'visites_autorites.*', 'activites_rentree.*', 'ventes_denrees.*'];

    private const EMPLOI_DU_TEMPS = ['emploi_du_temps.*', 'edt_elements.*', 'seances.*', 'salles.*'];

    // Tarifs, échéancier et corrections à la situation d'un élève : ce qui
    // décide d'un montant, à distinguer de l'encaissement.
    private const PARAMETRAGE_FINANCE = [
        'tarifs.*', 'frais_annexes.*', 'tranches_scolarite.*',
        'remises.*', 'moratoires.*', 'dettes_anterieures.*',
        'budget_fonctionnement.*', 'assurances_scolaires.*', 'conseil_ecole.*', 'apee.*',
    ];

    /**
     * Les privilèges existants viennent désormais du catalogue applicatif
     * (App\Support\CataloguePermissions) : la liste suit les routes qui les
     * exigent, et le seeder ne fait que la refléter en base.
     *
     * `FonctionPermissionSeeder` réutilise ces mêmes ensembles pour composer
     * les groupes de privilèges des fonctions du référentiel — d'où la
     * visibilité publique.
     *
     * Un motif `entité.*` vaut pour toutes les actions de l'entité : passer
     * par {@see permissionsDuRole()} pour obtenir la liste développée.
     */
    public const ROLE_PERMISSIONS = [
        /*
         * `admin_etablissement` a été scindé en deux rôles distincts pour
         * refléter les deux organigrammes : `admin_ecole` dirige l'école
         * maternelle/primaire, `admin_college` dirige le collège technique
         * secondaire. Les deux démarrent avec le même socle de permissions ;
         * un futur ajustement fin (ex. retirer `bus.*` de l'un des deux)
         * pourra être fait séparément une fois le rollout stabilisé.
         */
        'admin_ecole' => [
            ...self::ETABLISSEMENT,
            'conseil_classe.*',
            ...self::PERSONNEL,
            ...self::CLASSES,
            'niveaux.*',
            'eleves.view',
            ...self::ELEVES,
            'pedagogie.view',
            ...self::PEDAGOGIE,
            'notes.view',
            'notes.create',
            'discipline.view',
            ...self::DISCIPLINE,
            ...self::INFIRMERIE,
            'bus.view',
            ...self::FLOTTE_BUS,
            'bus.souscrire',
            ...self::INVENTAIRE,
            ...self::INFRASTRUCTURES,
            'point_de_vente.*',
            'finance.view',
            ...self::PARAMETRAGE_FINANCE,
            'finance.encaisser',
            'finance.paie',
            'finance.budget',
            'finance.rapports',
            ...self::RAPPORT_RENTREE,
            'bulletins.view',
            'bulletins.publish',
            'annonces.view',
            'annonces.publish',
            'bibliotheque.view',
            'dashboard.view',
            'dashboard.pilotage',
            ...self::EMPLOI_DU_TEMPS,
            'appel.saisir',
            'revendications.*',
        ],
        'admin_college' => [
            ...self::ETABLISSEMENT,
            'conseil_classe.*',
            ...self::PERSONNEL,
            ...self::CLASSES,
            'niveaux.*',
            'eleves.view',
            ...self::ELEVES,
            'pedagogie.view',
            ...self::PEDAGOGIE,
            'notes.view',
            'notes.create',
            'discipline.view',
            ...self::DISCIPLINE,
            ...self::INFIRMERIE,
            'bus.view',
            ...self::FLOTTE_BUS,
            'bus.souscrire',
            ...self::INVENTAIRE,
            ...self::INFRASTRUCTURES,
            'point_de_vente.*',
            'finance.view',
            ...self::PARAMETRAGE_FINANCE,
            'finance.encaisser',
            'finance.paie',
            'finance.budget',
            'finance.rapports',
            ...self::RAPPORT_RENTREE,
            'bulletins.view',
            'bulletins.publish',
            'annonces.view',
            'annonces.publish',
            'bibliotheque.view',
            'dashboard.view',
            'dashboard.pilotage',
            ...self::EMPLOI_DU_TEMPS,
            'appel.saisir',
            'revendications.*',
        ],
        'censeur_sg' => [
            'conseil_classe.view',
            'personnel.view',
            'classes.view',
            'eleves.view',
            'pedagogie.view',
            'notes.view',
            'notes.create',
            'discipline.view',
            ...self::DISCIPLINE,
            ...self::INFIRMERIE,
            'bus.view',
            ...self::FLOTTE_BUS,
            'bus.souscrire',
            'bulletins.view',
            'bulletins.publish',
            'annonces.view',
            'bibliotheque.view',
            'dashboard.view',
            'dashboard.pilotage',
            ...self::EMPLOI_DU_TEMPS,
            'appel.saisir',
            'revendications.*',
        ],
        /*
         * Le surveillant général tient la discipline : absences, sanctions,
         * appel et bilan disciplinaire. Il consulte les bulletins sans les
         * publier et ne saisit pas de notes — c'est le censeur qui répond du
         * pédagogique. La table `classes` distinguait déjà les deux
         * responsables ; les rôles le font désormais aussi.
         */
        'surveillant_general' => [
            'personnel.view',
            'classes.view',
            'eleves.view',
            'discipline.view',
            ...self::DISCIPLINE,
            ...self::INFIRMERIE,
            'bus.view',
            ...self::FLOTTE_BUS,
            'bus.souscrire',
            'bulletins.view',
            'emploi_du_temps.view',
            'appel.saisir',
            'annonces.view',
            'bibliotheque.view',
            'dashboard.view',
            'revendications.view',
        ],
        'enseignant' => [
            'classes.view',
            'eleves.view',
            'eleves.situation',
            'pedagogie.view',
            'notes.view',
            'notes.create',
            'bulletins.view',
            'discipline.view',
            'annonces.view',
            'bibliotheque.view',
            'dashboard.view',
            'emploi_du_temps.view',
            'appel.saisir',
            'revendications.view',
        ],
        'econome' => [
            'eleves.view',
            ...self::INVENTAIRE,
            ...self::INFRASTRUCTURES,
            'point_de_vente.*',
            'finance.view',
            ...self::PARAMETRAGE_FINANCE,
            'finance.encaisser',
            'finance.rapports',
            'annonces.view',
            'bibliotheque.view',
            'dashboard.view',
        ],
        /*
         * Le vendeur écoule le stock au comptoir et tient la fiche des
         * articles — pas de `dashboard.view` : le tableau de bord
         * d'établissement (effectifs élèves, personnel…) ne le concerne pas,
         * son accueil est directement le point de vente (cf.
         * redirectionParDefaut côté web).
         *
         * `eleves.view` reste nécessaire pour l'API — chercher un élève au
         * comptoir (vente à crédit) — mais l'écran Élèves et l'onglet
         * Documents lui restent fermés : le frontend masque ces accès pour
         * ce rôle spécifiquement (cf. estVendeur côté web/mobile), la
         * permission ne fait qu'ouvrir la donnée nécessaire au sélecteur.
         */
        'vendeur' => [
            ...self::INVENTAIRE,
            'point_de_vente.*',
            'eleves.view',
        ],
        'parent' => [
            'eleves.view',
            'notes.view',
            'discipline.view',
            'finance.view',
            'annonces.view',
        ],
        /*
         * Portail élève : lecture seule sur son propre dossier — pas de
         * finance.* (réservée au tuteur), aucune écriture. Cf. CompteEleveService
         * et EleveEspaceController, qui bornent chaque requête à la fiche du
         * compte connecté quel que soit le privilège porté ici.
         */
        'eleve' => [
            'notes.view',
            'annonces.view',
            'discipline.view',
            'infirmerie.view',
            'emploi_du_temps.view',
            'bulletins.view',
        ],
        /*
         * Gabarits de fonctions de soutien (infirmier, chauffeur, agents de
         * sécurité/entretien) : jamais assignés directement à un utilisateur
         * via assignRole, uniquement copiés sur une FonctionReferentiel par
         * FonctionPermissionSeeder — cf. FonctionRoles::CORRESPONDANCES.
         */
        'infirmier' => [
            ...self::INFIRMERIE,
            'eleves.view',
            'dashboard.view',
        ],
        'chauffeur' => [
            'bus.view',
        ],
        // Intentionnellement quasi vide : accès authentifié sans donnée
        // élève/personnel par défaut, même logique que `vendeur` pour
        // `dashboard.view`. Un super admin peut étendre depuis /permissions.
        'agent_securite' => [],
        'agent_entretien' => [],
    ];

    /**
     * Clés de ROLE_PERMISSIONS qui ne sont que des gabarits de fonctions
     * (jamais assignées à un utilisateur via assignRole) : `run()` ne doit
     * pas leur créer de ligne `Role` Spatie, seule FonctionPermissionSeeder
     * les lit directement dans ROLE_PERMISSIONS.
     */
    public const FONCTIONS_SANS_ROLE = ['infirmier', 'chauffeur', 'agent_securite', 'agent_entretien'];

    /**
     * Privilèges d'un rôle, motifs `entité.*` développés.
     *
     * @return list<string>
     */
    public static function permissionsDuRole(string $role): array
    {
        return CataloguePermissions::developper(self::ROLE_PERMISSIONS[$role] ?? []);
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $catalogue = CataloguePermissions::codes();

        foreach ($catalogue as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Un privilège retiré du catalogue ne protège plus rien : le laisser en
        // base le laisserait apparaître dans l'écran d'administration.
        Permission::where('guard_name', 'web')->whereNotIn('name', $catalogue)->delete();

        // super_admin : accès total, géré via Gate::before plutôt qu'une liste à maintenir.
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions($catalogue);

        foreach (array_keys(self::ROLE_PERMISSIONS) as $roleName) {
            if (in_array($roleName, self::FONCTIONS_SANS_ROLE, true)) {
                continue;
            }

            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions(self::permissionsDuRole($roleName));
        }
    }
}