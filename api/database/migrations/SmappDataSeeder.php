<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * SmappDataSeeder
 *
 * Réinjecte l'intégralité des données du dump smapp.sql (72 tables, 6973 lignes)
 * dans l'ordre respectant les contraintes de clés étrangères.
 *
 * Les vérifications de clés étrangères sont désactivées le temps du seed car
 * `departements` et `personnels` se référencent mutuellement (head_personnel_id / departement_id).
 *
 * Usage :
 *   php artisan db:seed --class=Database\\Seeders\\SmappDataSeeder
 * ou depuis DatabaseSeeder::run() : $this->call(SmappDataSeeder::class);
 */
class SmappDataSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        $this->call([
            \Database\Seeders\SmappData\ComplexesSeeder::class,
            \Database\Seeders\SmappData\SchoolsSeeder::class,
            \Database\Seeders\SmappData\AnneeScolairesSeeder::class,
            \Database\Seeders\SmappData\DepartementsSeeder::class,
            \Database\Seeders\SmappData\FonctionReferentielSeeder::class,
            \Database\Seeders\SmappData\SousSystemesSeeder::class,
            \Database\Seeders\SmappData\NiveauxSeeder::class,
            \Database\Seeders\SmappData\UsersSeeder::class,
            \Database\Seeders\SmappData\PersonnelsSeeder::class,
            \Database\Seeders\SmappData\NiveauScolairesSeeder::class,
            \Database\Seeders\SmappData\ClassesSeeder::class,
            \Database\Seeders\SmappData\ElevesSeeder::class,
            \Database\Seeders\SmappData\TrimestresSeeder::class,
            \Database\Seeders\SmappData\AbsenceTrimestresSeeder::class,
            \Database\Seeders\SmappData\ActivityLogsSeeder::class,
            \Database\Seeders\SmappData\AnnoncesSeeder::class,
            \Database\Seeders\SmappData\AvancesSalaireSeeder::class,
            \Database\Seeders\SmappData\AvanceRemboursementsSeeder::class,
            \Database\Seeders\SmappData\BulletinsPaieSeeder::class,
            \Database\Seeders\SmappData\BulletinPaieLignesSeeder::class,
            \Database\Seeders\SmappData\BusVehiculesSeeder::class,
            \Database\Seeders\SmappData\BusTrajetsSeeder::class,
            \Database\Seeders\SmappData\BusArretsSeeder::class,
            \Database\Seeders\SmappData\BusAffectationsSeeder::class,
            \Database\Seeders\SmappData\CacheSeeder::class,
            \Database\Seeders\SmappData\CacheLocksSeeder::class,
            \Database\Seeders\SmappData\MatieresSeeder::class,
            \Database\Seeders\SmappData\ClasseMatieresSeeder::class,
            \Database\Seeders\SmappData\ChampsPersonnalisesSeeder::class,
            \Database\Seeders\SmappData\ComptesComptablesSeeder::class,
            \Database\Seeders\SmappData\DepensesSeeder::class,
            \Database\Seeders\SmappData\DocumentReferencesSeeder::class,
            \Database\Seeders\SmappData\DossiersScolariteSeeder::class,
            \Database\Seeders\SmappData\FraisAnnexesSeeder::class,
            \Database\Seeders\SmappData\DossierFraisAnnexesSeeder::class,
            \Database\Seeders\SmappData\EcrituresComptablesSeeder::class,
            \Database\Seeders\SmappData\TuteursSeeder::class,
            \Database\Seeders\SmappData\EleveTuteurSeeder::class,
            \Database\Seeders\SmappData\EmploisDuTempsSeeder::class,
            \Database\Seeders\SmappData\SequencesSeeder::class,
            \Database\Seeders\SmappData\ProgressionItemsSeeder::class,
            \Database\Seeders\SmappData\EvaluationsSeeder::class,
            \Database\Seeders\SmappData\EvaluationQuestionsSeeder::class,
            \Database\Seeders\SmappData\FailedJobsSeeder::class,
            \Database\Seeders\SmappData\PermissionsSeeder::class,
            \Database\Seeders\SmappData\FonctionPermissionSeeder::class,
            \Database\Seeders\SmappData\FonctionsSeeder::class,
            \Database\Seeders\SmappData\GrillesFraisSeeder::class,
            \Database\Seeders\SmappData\InventaireArticlesSeeder::class,
            \Database\Seeders\SmappData\JobBatchesSeeder::class,
            \Database\Seeders\SmappData\JobsSeeder::class,
            \Database\Seeders\SmappData\SeancesSeeder::class,
            \Database\Seeders\SmappData\LeconSeanceSeeder::class,
            \Database\Seeders\SmappData\MigrationsSeeder::class,
            \Database\Seeders\SmappData\ModelHasPermissionsSeeder::class,
            \Database\Seeders\SmappData\RolesSeeder::class,
            \Database\Seeders\SmappData\ModelHasRolesSeeder::class,
            \Database\Seeders\SmappData\NotesSeeder::class,
            \Database\Seeders\SmappData\NotificationsInternesSeeder::class,
            \Database\Seeders\SmappData\PasswordResetTokensSeeder::class,
            \Database\Seeders\SmappData\PersonalAccessTokensSeeder::class,
            \Database\Seeders\SmappData\PresencesSeeder::class,
            \Database\Seeders\SmappData\RemunerationsSeeder::class,
            \Database\Seeders\SmappData\RevendicationsSeeder::class,
            \Database\Seeders\SmappData\RoleHasPermissionsSeeder::class,
            \Database\Seeders\SmappData\SanctionsSeeder::class,
            \Database\Seeders\SmappData\SchoolNiveauSeeder::class,
            \Database\Seeders\SmappData\SessionsSeeder::class,
            \Database\Seeders\SmappData\SettingsSeeder::class,
            \Database\Seeders\SmappData\VersementsSeeder::class,
            \Database\Seeders\SmappData\VersementLignesSeeder::class,
            \Database\Seeders\SmappData\VisitesInfirmerieSeeder::class,
        ]);

        Schema::enableForeignKeyConstraints();
    }
}
