<?php

namespace Database\Seeders;

use App\Support\CataloguePermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Les privilèges existants viennent désormais du catalogue applicatif
     * (App\Support\CataloguePermissions) : la liste suit les routes qui les
     * exigent, et le seeder ne fait que la refléter en base.
     *
     * `FonctionPermissionSeeder` réutilise ces mêmes ensembles pour composer
     * les groupes de privilèges des fonctions du référentiel — d'où la
     * visibilité publique.
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
            'ecoles.manage',
            'conseil_classe.view',
            'conseil_classe.manage',
            'personnel.view',
            'personnel.manage',
            'classes.view',
            'classes.manage',
            'niveaux.view',
            'niveaux.manage',
            'eleves.view',
            'eleves.manage',
            'pedagogie.view',
            'pedagogie.manage',
            'notes.view',
            'notes.create',
            'discipline.view',
            'discipline.manage',
            'infirmerie.view',
            'infirmerie.manage',
            'bus.view',
            'bus.manage',
            'inventaire.view',
            'inventaire.manage',
            'infrastructures.view',
            'infrastructures.manage',
            'point_de_vente.view',
            'point_de_vente.vendre',
            'point_de_vente.manage',
            'finance.view',
            'finance.manage',
            'finance.encaisser',
            'finance.paie',
            'finance.budget',
            'finance.rapports',
            'rapport_rentree.view',
            'rapport_rentree.manage',
            'bulletins.view',
            'bulletins.publish',
            'annonces.view',
            'annonces.publish',
            'dashboard.view',
            'emploi_du_temps.view',
            'emploi_du_temps.manage',
            'appel.manage',
            'revendications.view',
            'revendications.manage',
        ],
        'admin_college' => [
            'ecoles.manage',
            'conseil_classe.view',
            'conseil_classe.manage',
            'personnel.view',
            'personnel.manage',
            'classes.view',
            'classes.manage',
            'niveaux.view',
            'niveaux.manage',
            'eleves.view',
            'eleves.manage',
            'pedagogie.view',
            'pedagogie.manage',
            'notes.view',
            'notes.create',
            'discipline.view',
            'discipline.manage',
            'infirmerie.view',
            'infirmerie.manage',
            'bus.view',
            'bus.manage',
            'inventaire.view',
            'inventaire.manage',
            'infrastructures.view',
            'infrastructures.manage',
            'point_de_vente.view',
            'point_de_vente.vendre',
            'point_de_vente.manage',
            'finance.view',
            'finance.manage',
            'finance.encaisser',
            'finance.paie',
            'finance.budget',
            'finance.rapports',
            'rapport_rentree.view',
            'rapport_rentree.manage',
            'bulletins.view',
            'bulletins.publish',
            'annonces.view',
            'annonces.publish',
            'dashboard.view',
            'emploi_du_temps.view',
            'emploi_du_temps.manage',
            'appel.manage',
            'revendications.view',
            'revendications.manage',
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
            'discipline.manage',
            'infirmerie.view',
            'infirmerie.manage',
            'bus.view',
            'bus.manage',
            'bulletins.view',
            'bulletins.publish',
            'annonces.view',
            'dashboard.view',
            'emploi_du_temps.view',
            'emploi_du_temps.manage',
            'appel.manage',
            'revendications.view',
            'revendications.manage',
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
            'discipline.manage',
            'infirmerie.view',
            'infirmerie.manage',
            'bus.view',
            'bus.manage',
            'bulletins.view',
            'emploi_du_temps.view',
            'appel.manage',
            'annonces.view',
            'dashboard.view',
            'revendications.view',
        ],
        'enseignant' => [
            'classes.view',
            'eleves.view',
            'pedagogie.view',
            'notes.view',
            'notes.create',
            'bulletins.view',
            'discipline.view',
            'annonces.view',
            'dashboard.view',
            'emploi_du_temps.view',
            'appel.manage',
            'revendications.view',
        ],
        'econome' => [
            'eleves.view',
            'inventaire.view',
            'inventaire.manage',
            'infrastructures.view',
            'infrastructures.manage',
            'point_de_vente.view',
            'point_de_vente.vendre',
            'point_de_vente.manage',
            'finance.view',
            'finance.manage',
            'finance.encaisser',
            'finance.rapports',
            'annonces.view',
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
            'inventaire.view',
            'inventaire.manage',
            'point_de_vente.view',
            'point_de_vente.vendre',
            'point_de_vente.manage',
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
         * finance.* (réservée au tuteur), pas de *.manage. Cf. CompteEleveService
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
            'infirmerie.view',
            'infirmerie.manage',
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

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            if (in_array($roleName, self::FONCTIONS_SANS_ROLE, true)) {
                continue;
            }

            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }
    }
}
