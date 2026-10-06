<?php

use App\Http\Controllers\Api\V1\AbsenceController;
use App\Http\Controllers\Api\V1\ActiviteRentreeController;
use App\Http\Controllers\Api\V1\AnneeScolaireController;
use App\Http\Controllers\Api\V1\AnnonceController;
use App\Http\Controllers\Api\V1\BibliothequeController;
use App\Http\Controllers\Api\V1\ApeeController;
use App\Http\Controllers\Api\V1\AppreciationController;
use App\Http\Controllers\Api\V1\ArchiveClasseController;
use App\Http\Controllers\Api\V1\AssuranceScolaireController;
use App\Http\Controllers\Api\V1\AttestationController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvanceSalaireController;
use App\Http\Controllers\Api\V1\BudgetFonctionnementController;
use App\Http\Controllers\Api\V1\BudgetPersonnelController;
use App\Http\Controllers\Api\V1\BulletinController;
use App\Http\Controllers\Api\V1\BulletinPrimaireController;
use App\Http\Controllers\Api\V1\BusAffectationController;
use App\Http\Controllers\Api\V1\BusPaiementController;
use App\Http\Controllers\Api\V1\BusRemplacementController;
use App\Http\Controllers\Api\V1\BusTrajetController;
use App\Http\Controllers\Api\V1\BusVehiculeController;
use App\Http\Controllers\Api\V1\CarteScolaireController;
use App\Http\Controllers\Api\V1\ChauffeurEspaceController;
use App\Http\Controllers\Api\V1\ClasseController;
use App\Http\Controllers\Api\V1\ClasseMatiereController;
use App\Http\Controllers\Api\V1\CalendrierScolaireController;
use App\Http\Controllers\Api\V1\CompetenceController;
use App\Http\Controllers\Api\V1\CompteController;
use App\Http\Controllers\Api\V1\ConseilClasseController;
use App\Http\Controllers\Api\V1\ConseilEcoleController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DemandeArticleInventaireAdminController;
use App\Http\Controllers\Api\V1\DemandeAvanceSalaireAdminController;
use App\Http\Controllers\Api\V1\DepartementController;
use App\Http\Controllers\Api\V1\DepenseController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DesktopProvisioningController;
use App\Http\Controllers\Api\V1\DetteAnterieureController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\EleveController;
use App\Http\Controllers\Api\V1\EleveEspaceController;
use App\Http\Controllers\Api\V1\EleveRapportsController;
use App\Http\Controllers\Api\V1\EmploiDuTempsController;
use App\Http\Controllers\Api\V1\EmploiDuTempsElementController;
use App\Http\Controllers\Api\V1\TroncCommunGroupeController;
use App\Http\Controllers\Api\V1\EnseignantController;
use App\Http\Controllers\Api\V1\EtatSyntheseController;
use App\Http\Controllers\Api\V1\EvaluationController;
use App\Http\Controllers\Api\V1\BanqueController;
use App\Http\Controllers\Api\V1\RegleValidationSeanceController;
use App\Http\Controllers\Api\V1\FonctionReferentielController;
use App\Http\Controllers\Api\V1\InfrastructureController;
use App\Http\Controllers\Api\V1\InsolvablesController;
use App\Http\Controllers\Api\V1\InventaireController;
use App\Http\Controllers\Api\V1\JustificationAbsenceAdminController;
use App\Http\Controllers\Api\V1\ListeClassePersonnaliseeController;
use App\Http\Controllers\Api\V1\ListeEnseignantPersonnaliseeController;
use App\Http\Controllers\Api\V1\ListeElevesController;
use App\Http\Controllers\Api\V1\MaJourneeController;
use App\Http\Controllers\Api\V1\MalaiseReferentielController;
use App\Http\Controllers\Api\V1\MatiereController;
use App\Http\Controllers\Api\V1\MatriculeNationalController;
use App\Http\Controllers\Api\V1\MigrationStatusController;
use App\Http\Controllers\Api\V1\ModificationEleveAdminController;
use App\Http\Controllers\Api\V1\RouteListController;
use App\Http\Controllers\Api\V1\MoratoireController;
use App\Http\Controllers\Api\V1\NiveauController;
use App\Http\Controllers\Api\V1\NiveauScolaireController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\NoteEleveController;
use App\Http\Controllers\Api\V1\NotePrimaireController;
use App\Http\Controllers\Api\V1\NotificationInterneController;
use App\Http\Controllers\Api\V1\ObservationAdminController;
use App\Http\Controllers\Api\V1\PaieController;
use App\Http\Controllers\Api\V1\ParentEspaceController;
use App\Http\Controllers\Api\V1\ParentPreinscriptionController;
use App\Http\Controllers\Api\V1\ParentUsageStatsController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\PersonnelController;
use App\Http\Controllers\Api\V1\PersonnelEspaceController;
use App\Http\Controllers\Api\V1\PhotoExamenController;
use App\Http\Controllers\Api\V1\PointDeVenteController;
use App\Http\Controllers\Api\V1\PreinscriptionAdminController;
use App\Http\Controllers\Api\V1\ProgressionController;
use App\Http\Controllers\Api\V1\PushDiagnosticController;
use App\Http\Controllers\Api\V1\RapportFinancierController;
use App\Http\Controllers\Api\V1\RapportRentreeExportController;
use App\Http\Controllers\Api\V1\RapportRentreeTexteController;
use App\Http\Controllers\Api\V1\RapportTrimestreExportController;
use App\Http\Controllers\Api\V1\RapportTrimestreTexteController;
use App\Http\Controllers\Api\V1\RemiseController;
use App\Http\Controllers\Api\V1\SalleController;
use App\Http\Controllers\Api\V1\RemunerationController;
use App\Http\Controllers\Api\V1\ResultatController;
use App\Http\Controllers\Api\V1\ResultatPrimaireController;
use App\Http\Controllers\Api\V1\RevendicationController;
use App\Http\Controllers\Api\V1\SanctionController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\SmsCallbackController;
use App\Http\Controllers\Api\V1\SituationEnseignantController;
use App\Http\Controllers\Api\V1\ScolariteController;
use App\Http\Controllers\Api\V1\SeanceController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\SousSystemeController;
use App\Http\Controllers\Api\V1\StatistiqueController;
use App\Http\Controllers\Api\V1\SuiviActiviteController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TarifsController;
use App\Http\Controllers\Api\V1\TrancheScolariteController;
use App\Http\Controllers\Api\V1\TrimestreController;
use App\Http\Controllers\Api\V1\TuteurController;
use App\Http\Controllers\Api\V1\VenteDenreeController;
use App\Http\Controllers\Api\V1\VerificationBulletinController;
use App\Http\Controllers\Api\V1\VerificationController;
use App\Http\Controllers\Api\V1\VerificationEmploiDuTempsController;
use App\Http\Controllers\Api\V1\VerificationVersementBusController;
use App\Http\Controllers\Api\V1\VerificationVersementController;
use App\Http\Controllers\Api\V1\VisiteAutoriteController;
use App\Http\Controllers\Api\V1\VisiteInfirmerieController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    // Mot de passe oublié : ces deux routes sont volontairement publiques
    // (pas de session à ce stade) et limitées en fréquence pour empêcher
    // l'envoi massif de courriels ou l'essai exhaustif d'un code à 6 chiffres.
    Route::post('auth/mot-de-passe-oublie', [AuthController::class, 'demanderOtp'])
        ->middleware('throttle:3,1')
        ->name('auth.mot-de-passe-oublie');
    Route::post('auth/reinitialiser-mot-de-passe', [AuthController::class, 'reinitialiserMotDePasseOtp'])
        ->middleware('throttle:10,1')
        ->name('auth.reinitialiser-mot-de-passe');

    // Provisioning d'une instance locale (client desktop offline) : aucun
    // utilisateur local n'existe encore avant `provisionner`, et `connexion`
    // EST le mécanisme de connexion de cette instance — plusieurs comptes
    // peuvent y être provisionnés, chacun avec son propre mot de passe local
    // (cf. DesktopProvisioningController).
    Route::post('desktop/provisionner', [DesktopProvisioningController::class, 'provisionner'])->name('desktop.provisionner');
    Route::post('desktop/connexion', [DesktopProvisioningController::class, 'connexion'])->name('desktop.connexion');

    // Vérification publique d'authenticité d'un bulletin (QR code) : accessible
    // sans authentification, un tiers externe scanne depuis son téléphone.
    Route::get('verification-bulletin/{eleveId}/{trimestreId}/{signature}', [VerificationBulletinController::class, 'show'])
        ->name('verification-bulletin.show');

    Route::get('verification-emploi-du-temps/{classeId}/{anneeId}/{signature}', [VerificationEmploiDuTempsController::class, 'show'])
        ->name('verification-emploi-du-temps.show');

    // Vérification publique d'authenticité d'un reçu de versement (QR code) :
    // même principe que ci-dessus, appliqué au reçu de paiement.
    Route::get('verification-versement/{versementId}/{signature}', [VerificationVersementController::class, 'show'])
        ->name('verification-versement.show');

    // Même principe pour un reçu de transport scolaire — registre séparé, donc route séparée.
    Route::get('verification-versement-bus/{versementId}/{signature}', [VerificationVersementBusController::class, 'show'])
        ->name('verification-versement-bus.show');

    // Point d'entrée unique pour l'app mobile (scan QR) : reçoit tel quel le
    // chemin de vérification encodé dans le QR (ex.
    // "verification-bulletin/42/3/<signature>") et délègue au bon type de
    // document ci-dessus, en aplatissant la réponse dans une forme commune —
    // voir VerificationController.
    Route::get('verify/{path}', [VerificationController::class, 'show'])
        ->where('path', '.*')
        ->name('verify.show');

    // Le PDF lui-même, derrière le même chemin signé, pour comparer le papier
    // présenté au document authentique (cf. VerificationController::document).
    Route::get('verify-document/{path}', [VerificationController::class, 'document'])
        ->where('path', '.*')
        ->name('verify.document');

    // Aperçu PDF d'un document Word de la bibliothèque : lien signé et
    // temporaire, remis uniquement dans les listes que le compte voit déjà
    // (cf. BibliothequeDocument::apercu_url).
    Route::get('bibliotheque/{id}/apercu', [BibliothequeController::class, 'apercu'])
        ->whereNumber('id')
        ->middleware('signed')
        ->name('bibliotheque.apercu');

    // Callback DLR d'Orange (delivery report) : Orange n'authentifie pas cet
    // appel ("No authentication" côté portail Orange), donc pas de
    // middleware d'auth ici — le contrôleur ne fait qu'écrire un log
    // (jamais lu ni exposé côté client) et répond toujours 200.
    Route::post('sms/dlr-callback', [SmsCallbackController::class, 'handleDlr'])
        ->name('sms.dlr-callback');

    // `mot_de_passe` barre tout l'espace authentifié tant que le mot de passe
    // provisoire n'a pas été remplacé, à l'exception des routes qui permettent
    // justement d'en sortir (cf. ExigerMotDePasseRenouvele).
    Route::middleware(['auth:sanctum', 'mot_de_passe'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/mot-de-passe', [AuthController::class, 'changerMotDePasse'])->name('auth.mot-de-passe');
        Route::put('auth/profil', [AuthController::class, 'updateProfil'])->name('auth.profil');

        Route::get('system/migrations/status', MigrationStatusController::class)
            ->middleware('super_admin')
            ->name('system.migrations.status');
        Route::get('system/routes', RouteListController::class)
            ->middleware('super_admin')
            ->name('system.routes');

        // Référentiel global, non scopé par établissement.
        Route::get('niveaux', [NiveauController::class, 'index'])->name('niveaux.index')->middleware('permission:niveaux.view');
        // `export`/`modele`/`import` exigent un périmètre résolu (Tenant::schoolIds())
        // que le reste de ce contrôleur (référentiel global) n'a pas besoin de charger —
        // 'tenant' ajouté explicitement à ces trois-là seulement.
        Route::get('niveaux/export', [NiveauController::class, 'export'])->name('niveaux.export')->middleware(['tenant', 'permission:niveaux.view']);
        Route::get('niveaux/modele', [NiveauController::class, 'modele'])->name('niveaux.modele')->middleware('permission:niveaux.view');
        Route::post('niveaux/import', [NiveauController::class, 'import'])->name('niveaux.import')->middleware(['tenant', 'permission:niveaux.import']);
        Route::post('niveaux', [NiveauController::class, 'store'])->name('niveaux.store')->middleware('permission:niveaux.create');
        Route::get('niveaux/{id}', [NiveauController::class, 'show'])->name('niveaux.show')->middleware('permission:niveaux.view');
        Route::put('niveaux/{id}', [NiveauController::class, 'update'])->name('niveaux.update')->middleware('permission:niveaux.update');
        Route::delete('niveaux/{id}', [NiveauController::class, 'destroy'])->name('niveaux.destroy')->middleware('permission:niveaux.delete');
        Route::post('niveaux/batch-delete', [NiveauController::class, 'batchDestroy'])->name('niveaux.batch-destroy')->middleware('permission:niveaux.delete');
        Route::post('niveaux/batch-update', [NiveauController::class, 'batchUpdate'])->name('niveaux.batch-update')->middleware('permission:niveaux.update');

        // Toutes les routes métier (établissement, personnel, classes, élèves, ...)
        // sont scopées par établissement + niveau via le middleware `tenant`.
        // `idempotence` couvre d'un coup les 84 écritures métier : le mobile
        // peut rejouer n'importe laquelle sans risque de doublon, sans avoir
        // à déclarer route par route lesquelles sont rejouables.
        Route::middleware(['tenant', 'idempotence', 'outbox-local'])->group(function () {

            /*
             * Synchronisation de l'application mobile. Aucun privilège propre :
             * l'endpoint ne renvoie que les entités que l'utilisateur peut déjà
             * consulter, en filtrant lui-même sur ses privilèges (cf.
             * `RegistreSync`). Un privilège « sync.view » ne protégerait rien
             * de plus et créerait un second endroit où gérer les accès.
             */
            Route::get('sync', [SyncController::class, 'pull'])->name('sync.pull');
            Route::post('sync', [SyncController::class, 'push'])->name('sync.push');
            Route::get('sync/comptage', [SyncController::class, 'comptage'])->name('sync.comptage');

            // Pilotage de la synchronisation d'une instance locale (client
            // desktop). Sans objet sur le serveur distant lui-même.
            Route::get('desktop/statut-sync', [DesktopProvisioningController::class, 'statutSync'])->name('desktop.statut-sync');
            Route::post('desktop/synchroniser', [DesktopProvisioningController::class, 'synchroniser'])->name('desktop.synchroniser');

            // Enregistrement de l'appareil pour les notifications push. Aucun
            // privilège : tout utilisateur authentifié a le droit d'être
            // notifié de ce qui le concerne déjà.
            Route::post('appareils', [DeviceTokenController::class, 'store'])->name('appareils.store');
            Route::delete('appareils', [DeviceTokenController::class, 'destroy'])->name('appareils.destroy');

            /*
             * Administration des privilèges. Protégée par le rôle et non par un
             * privilège : un droit qui permettrait de s'octroyer tous les
             * autres ne protégerait rien.
             */
            Route::middleware('super_admin')->group(function () {
                Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
                Route::get('fonctions-referentiel/{id}/permissions', [PermissionController::class, 'show'])->name('permissions.show');
                Route::put('fonctions-referentiel/{id}/permissions', [PermissionController::class, 'update'])->name('permissions.update');

                // Administration des comptes utilisateurs : liste, activité, et
                // réinitialisation de mot de passe — même logique de protection
                // par rôle, pour la même raison. Préfixe distinct de `comptes`
                // (comptes comptables, cf. DepenseController::comptes) : un autre
                // sens du mot « compte ».
                Route::get('comptes-utilisateurs', [CompteController::class, 'index'])->name('comptes-utilisateurs.index');
                Route::get('comptes-utilisateurs/{id}/activite', [CompteController::class, 'activite'])->name('comptes-utilisateurs.activite');
                Route::post('comptes-utilisateurs/{id}/reinitialiser-mot-de-passe', [CompteController::class, 'reinitialiserMotDePasse'])->name('comptes-utilisateurs.reinitialiser-mot-de-passe');
                Route::put('comptes-utilisateurs/{id}/ecoles', [CompteController::class, 'attribuerEcoles'])->name('comptes-utilisateurs.attribuer-ecoles');
                Route::put('comptes-utilisateurs/{id}/super-admin', [CompteController::class, 'basculerSuperAdmin'])->name('comptes-utilisateurs.basculer-super-admin');
                Route::post('comptes-utilisateurs/reinitialiser-mots-de-passe-jamais-connectes', [CompteController::class, 'reinitialiserMotsDePasseJamaisConnectes'])->name('comptes-utilisateurs.reinitialiser-mots-de-passe-jamais-connectes');
                Route::post('comptes-utilisateurs/{id}/bloquer', [CompteController::class, 'bloquer'])->name('comptes-utilisateurs.bloquer');
                Route::post('comptes-utilisateurs/{id}/debloquer', [CompteController::class, 'debloquer'])->name('comptes-utilisateurs.debloquer');
                Route::delete('comptes-utilisateurs/{id}', [CompteController::class, 'destroy'])->name('comptes-utilisateurs.destroy');

                // Diagnostic des notifications push : vérifiable depuis un
                // simple appel API, sans accès au `.env` du serveur.
                Route::get('diagnostics/push', [PushDiagnosticController::class, 'index'])->name('diagnostics.push');
                Route::post('diagnostics/push/test', [PushDiagnosticController::class, 'test'])->name('diagnostics.push.test');

                // Console d'audit (interface développeur) : journal exhaustif
                // de toutes les requêtes, alimenté par JournaliserAudit.
                Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
                Route::get('audit/stats', [AuditLogController::class, 'stats'])->name('audit.stats');
                Route::get('audit/filtres', [AuditLogController::class, 'filtres'])->name('audit.filtres');
                Route::get('audit/export', [AuditLogController::class, 'export'])->name('audit.export');
                Route::get('audit/{id}', [AuditLogController::class, 'show'])->whereNumber('id')->name('audit.show');

                // Centre de documents : tout document de la plateforme, à
                // l'unité ou en paquet ZIP produit par lots (cf. DocumentController).
                Route::get('documents/catalogue', [DocumentController::class, 'catalogue'])->name('documents.catalogue');
                Route::get('documents/cibles', [DocumentController::class, 'cibles'])->name('documents.cibles');
                Route::post('documents/paquets', [DocumentController::class, 'preparer'])->name('documents.paquets.preparer');
                Route::post('documents/paquets/{token}/traiter', [DocumentController::class, 'traiter'])->name('documents.paquets.traiter');
                Route::get('documents/paquets/{token}/telecharger', [DocumentController::class, 'telecharger'])->name('documents.paquets.telecharger');
                Route::delete('documents/paquets/{token}', [DocumentController::class, 'supprimer'])->name('documents.paquets.supprimer');
            });

            Route::middleware('permission:personnel.view')->group(function () {
                Route::get('departements', [DepartementController::class, 'index'])->name('departements.index');
                Route::get('departements/export', [DepartementController::class, 'export'])->name('departements.export');
                Route::get('departements/modele', [DepartementController::class, 'modele'])->name('departements.modele');
                Route::get('departements/{id}', [DepartementController::class, 'show'])->name('departements.show');
                Route::get('departements/{id}/statistiques/pedagogiques', [DepartementController::class, 'statsPedagogiques'])->name('departements.stats-pedagogiques');
                Route::get('departements/{id}/statistiques/pedagogiques/export-pdf', [DepartementController::class, 'exportPdfStatistiques'])->name('departements.export-pdf-stats');
                Route::get('fonctions-referentiel', [FonctionReferentielController::class, 'index'])->name('fonctions-referentiel.index');
                Route::get('fonctions-referentiel/export', [FonctionReferentielController::class, 'export'])->name('fonctions-referentiel.export');
                Route::get('fonctions-referentiel/modele', [FonctionReferentielController::class, 'modele'])->name('fonctions-referentiel.modele');
                Route::get('fonctions-referentiel/{id}', [FonctionReferentielController::class, 'show'])->name('fonctions-referentiel.show');
                Route::get('banques', [BanqueController::class, 'index'])->name('banques.index');
                Route::get('banques/export', [BanqueController::class, 'export'])->name('banques.export');
                Route::get('banques/modele', [BanqueController::class, 'modele'])->name('banques.modele');
                Route::get('banques/{id}', [BanqueController::class, 'show'])->name('banques.show');
                Route::get('regles-validation-seance', [RegleValidationSeanceController::class, 'index'])->name('regles-validation-seance.index');
                Route::get('personnels', [PersonnelController::class, 'index'])->name('personnels.index');
                Route::get('personnels/export', [PersonnelController::class, 'export'])->name('personnels.export');
                Route::get('personnels/modele', [PersonnelController::class, 'modele'])->name('personnels.modele');
                Route::get('personnels/fichier', [PersonnelController::class, 'fichier'])->name('personnels.fichier');
                Route::get('personnels/presences-journalieres/fiche', [PersonnelController::class, 'fichePresenceJournaliere'])->name('personnels.presences-journalieres.fiche');
                Route::get('personnels/presences-journalieres/modele', [PersonnelController::class, 'modelePresenceJournaliere'])->name('personnels.presences-journalieres.modele');
                Route::get('personnels/presences-journalieres/export', [PersonnelController::class, 'exportPresenceJournaliere'])->name('personnels.presences-journalieres.export');
                Route::get('personnels/liste-personnalisee/modeles', [ListeEnseignantPersonnaliseeController::class, 'modeles'])->name('personnels.liste-personnalisee.modeles.index');
                Route::post('personnels/liste-personnalisee/modeles', [ListeEnseignantPersonnaliseeController::class, 'storeModele'])->name('personnels.liste-personnalisee.modeles.store');
                Route::put('personnels/liste-personnalisee/modeles/{id}', [ListeEnseignantPersonnaliseeController::class, 'updateModele'])->name('personnels.liste-personnalisee.modeles.update');
                Route::delete('personnels/liste-personnalisee/modeles/{id}', [ListeEnseignantPersonnaliseeController::class, 'destroyModele'])->name('personnels.liste-personnalisee.modeles.destroy');
                Route::get('personnels/liste-personnalisee/pdf', [ListeEnseignantPersonnaliseeController::class, 'pdf'])->name('personnels.liste-personnalisee.pdf');
                Route::get('personnels/liste-personnalisee/word', [ListeEnseignantPersonnaliseeController::class, 'word'])->name('personnels.liste-personnalisee.word');
                Route::get('personnels/liste-personnalisee/excel', [ListeEnseignantPersonnaliseeController::class, 'excel'])->name('personnels.liste-personnalisee.excel');
                Route::get('personnels/rapport-mise-en-place', [PersonnelController::class, 'rapportMiseEnPlace'])->name('personnels.rapport-mise-en-place');
                Route::get('personnels/suivi-activite', [SuiviActiviteController::class, 'parPersonnel'])->name('personnels.suivi-activite');
                Route::get('personnels/suivi-activite/incoherences-presence', [SuiviActiviteController::class, 'incoherencesPresence'])->name('personnels.suivi-activite.incoherences-presence');
                // Route littérale avant le paramètre générique {id} ci-dessous, sinon
                // « identifiants » s'y ferait happer. Document sensible (mots de passe) :
                // exige `personnel.comptes` en plus du `.view` du groupe.
                Route::get('personnels/identifiants', [PersonnelController::class, 'identifiants'])
                    ->name('personnels.identifiants')->middleware('permission:personnel.comptes');
                Route::get('personnels/{id}', [PersonnelController::class, 'show'])->name('personnels.show');
                Route::get('personnels/{id}/fiche-identification/pdf', [PersonnelController::class, 'fichePdf'])->name('personnels.fiche-pdf');
                Route::get('personnels/{id}/fiche-identification/word', [PersonnelController::class, 'ficheWord'])->name('personnels.fiche-word');
            });

            Route::post('departements', [DepartementController::class, 'store'])->name('departements.store')->middleware('permission:departements.create');
            Route::post('departements/import', [DepartementController::class, 'import'])->name('departements.import')->middleware('permission:departements.import');
            Route::put('departements/{id}', [DepartementController::class, 'update'])->name('departements.update')->middleware('permission:departements.update');
            Route::delete('departements/{id}', [DepartementController::class, 'destroy'])->name('departements.destroy')->middleware('permission:departements.delete');

            Route::post('fonctions-referentiel', [FonctionReferentielController::class, 'store'])->name('fonctions-referentiel.store')->middleware('permission:fonctions.create');
            Route::post('fonctions-referentiel/import', [FonctionReferentielController::class, 'import'])->name('fonctions-referentiel.import')->middleware('permission:fonctions.import');
            Route::put('fonctions-referentiel/{id}', [FonctionReferentielController::class, 'update'])->name('fonctions-referentiel.update')->middleware('permission:fonctions.update');
            Route::delete('fonctions-referentiel/{id}', [FonctionReferentielController::class, 'destroy'])->name('fonctions-referentiel.destroy')->middleware('permission:fonctions.delete');
            Route::post('fonctions-referentiel/batch-delete', [FonctionReferentielController::class, 'batchDelete'])->name('fonctions-referentiel.batch-delete')->middleware('permission:fonctions.delete');

            Route::post('banques', [BanqueController::class, 'store'])->name('banques.store')->middleware('permission:banques.create');
            Route::post('banques/import', [BanqueController::class, 'import'])->name('banques.import')->middleware('permission:banques.import');
            Route::post('banques/{id}/depot', [BanqueController::class, 'deposer'])->name('banques.depot')->middleware('permission:banques.mouvements');
            Route::post('banques/{id}/retrait', [BanqueController::class, 'retirer'])->name('banques.retrait')->middleware('permission:banques.mouvements');
            Route::put('banques/{id}/depots/{mouvementId}', [BanqueController::class, 'modifierDepot'])->name('banques.depot.update')->middleware('permission:banques.mouvements');
            Route::put('banques/{id}', [BanqueController::class, 'update'])->name('banques.update')->middleware('permission:banques.update');
            Route::delete('banques/{id}', [BanqueController::class, 'destroy'])->name('banques.destroy')->middleware('permission:banques.delete');
            Route::post('banques/batch-delete', [BanqueController::class, 'batchDelete'])->name('banques.batch-delete')->middleware('permission:banques.delete');

            Route::post('regles-validation-seance', [RegleValidationSeanceController::class, 'store'])->name('regles-validation-seance.store')->middleware('permission:regles_seance.create');
            Route::put('regles-validation-seance/{id}', [RegleValidationSeanceController::class, 'update'])->name('regles-validation-seance.update')->middleware('permission:regles_seance.update');
            Route::delete('regles-validation-seance/{id}', [RegleValidationSeanceController::class, 'destroy'])->name('regles-validation-seance.destroy')->middleware('permission:regles_seance.delete');
            // Applique la règle au périmètre : lève les surcharges portées par
            // les fiches de personnel, qui repassent sous la règle.
            Route::post('regles-validation-seance/{id}/appliquer', [RegleValidationSeanceController::class, 'appliquer'])->name('regles-validation-seance.appliquer')->middleware('permission:regles_seance.update');

            Route::post('personnels', [PersonnelController::class, 'store'])->name('personnels.store')->middleware('permission:personnel.create');
            Route::put('personnels/{id}', [PersonnelController::class, 'update'])->name('personnels.update')->middleware('permission:personnel.update');
            Route::post('personnels/{id}/archive', [PersonnelController::class, 'archive'])->name('personnels.archive')->middleware('permission:personnel.archiver');
            Route::post('personnels/{id}/reactivate', [PersonnelController::class, 'reactivate'])->name('personnels.reactivate')->middleware('permission:personnel.archiver');
            Route::post('personnels/{id}/compte', [PersonnelController::class, 'createAccount'])->name('personnels.compte')->middleware('permission:personnel.comptes');
            Route::post('personnels/rattraper-telephones', [PersonnelController::class, 'rattraperTelephones'])->name('personnels.rattraper-telephones')->middleware('permission:personnel.update');
            Route::get('personnels/fusion-parent/apercu', [PersonnelController::class, 'apercuFusionComptesParent'])->name('personnels.fusion-parent.apercu')->middleware('permission:personnel.comptes');
            Route::post('personnels/fusion-parent', [PersonnelController::class, 'fusionnerComptesParent'])->name('personnels.fusion-parent')->middleware('permission:personnel.comptes');
            Route::post('personnels/import', [PersonnelController::class, 'import'])->name('personnels.import')->middleware('permission:personnel.import');
            Route::post('personnels/presences-journalieres/import', [PersonnelController::class, 'importPresenceJournaliere'])->name('personnels.presences-journalieres.import')->middleware('permission:personnel.presences');
            Route::post('personnels/presences-journalieres/import-ocr/apercu', [PersonnelController::class, 'apercuOcrPresenceJournaliere'])->name('personnels.presences-journalieres.import-ocr.apercu')->middleware('permission:personnel.presences');
            Route::post('personnels/presences-journalieres/import-ocr', [PersonnelController::class, 'importOcrPresenceJournaliere'])->name('personnels.presences-journalieres.import-ocr')->middleware('permission:personnel.presences');
            Route::post('personnels/suivi-activite/incoherences-presence/{seanceId}/annuler-validation', [SuiviActiviteController::class, 'annulerValidationPresence'])->name('personnels.suivi-activite.incoherences-presence.annuler-validation')->middleware('permission:personnel.presences');
            Route::get('personnels/{id}/attestation-employeur', [PersonnelController::class, 'attestationEmployeur'])->name('personnels.attestation')->middleware('permission:personnel.attestations');
            Route::delete('personnels/{id}', [PersonnelController::class, 'destroy'])->name('personnels.destroy')->middleware('permission:personnel.delete');
            Route::post('personnels/batch-delete', [PersonnelController::class, 'batchDelete'])->name('personnels.batch-delete')->middleware('permission:personnel.delete');
            Route::post('personnels/batch-fonction', [PersonnelController::class, 'batchFonction'])->name('personnels.batch-fonction')->middleware('permission:personnel.update');

            Route::middleware('permission:dashboard.view')->group(function () {
                Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
                Route::get('dashboard/indicateurs-pedagogiques', [DashboardController::class, 'indicateursPedagogiques'])->name('dashboard.indicateurs-pedagogiques');
                Route::get('dashboard/assiduite', [DashboardController::class, 'assiduite'])->name('dashboard.assiduite');
                Route::get('dashboard/anciens-reinscrits', [DashboardController::class, 'anciensReinscrits'])->name('dashboard.anciens-reinscrits');
                Route::get('dashboard/anciens-reinscrits/export', [DashboardController::class, 'exportAnciensReinscrits'])->name('dashboard.anciens-reinscrits.export');
            });

            // Pilotage en temps réel (cours en cours, appels en retard) :
            // réservé à la direction, pas à tous ceux qui voient le tableau de bord.
            Route::middleware('permission:dashboard.pilotage')->group(function () {
                Route::get('dashboard/pilotage', [DashboardController::class, 'pilotage'])->name('dashboard.pilotage');
            });

            // Journal complet des connexions/actions : réservé au super admin,
            // comme la carte « Activité récente » qui n'apparaît que pour lui.
            Route::middleware('super_admin')->group(function () {
                Route::get('dashboard/activite', [DashboardController::class, 'activiteRecente'])->name('dashboard.activite');
            });

            // Notifications internes : chacun ne voit que les siennes, aucun
            // privilège au-delà d'être authentifié et rattaché à l'école.
            Route::get('notifications', [NotificationInterneController::class, 'index'])->name('notifications.index');
            Route::get('notifications/non-lues', [NotificationInterneController::class, 'nonLues'])->name('notifications.non-lues');
            Route::post('notifications/{id}/lire', [NotificationInterneController::class, 'marquerLue'])->name('notifications.lire');
            Route::post('notifications/tout-lire', [NotificationInterneController::class, 'marquerToutesLues'])->name('notifications.tout-lire');

            Route::middleware('permission:annonces.view')->group(function () {
                Route::get('annonces', [AnnonceController::class, 'index'])->name('annonces.index');
            });

            Route::middleware('permission:annonces.publish')->group(function () {
                Route::post('annonces', [AnnonceController::class, 'store'])->name('annonces.store');
                Route::delete('annonces/{id}', [AnnonceController::class, 'destroy'])->name('annonces.destroy');
                Route::get('annonces/fonctions', [AnnonceController::class, 'fonctions'])->name('annonces.fonctions');
                Route::get('annonces/destinataires', [AnnonceController::class, 'destinataires'])->name('annonces.destinataires');
            });

            Route::middleware('permission:bibliotheque.view')->group(function () {
                Route::get('bibliotheque', [BibliothequeController::class, 'index'])->name('bibliotheque.index');
            });

            Route::post('bibliotheque', [BibliothequeController::class, 'store'])->name('bibliotheque.store')->middleware('permission:bibliotheque.create');
            Route::post('bibliotheque/import', [BibliothequeController::class, 'importer'])->name('bibliotheque.import')->middleware('permission:bibliotheque.create');
            Route::put('bibliotheque/{id}', [BibliothequeController::class, 'update'])->name('bibliotheque.update')->middleware('permission:bibliotheque.update');
            Route::delete('bibliotheque/{id}', [BibliothequeController::class, 'destroy'])->name('bibliotheque.destroy')->middleware('permission:bibliotheque.delete');
            Route::post('classes/{classeId}/emploi-du-temps/publier-pdf', [EmploiDuTempsController::class, 'publierPdf'])
                ->name('edt.publier-pdf')->middleware(['permission:bibliotheque.create', 'permission:emploi_du_temps.view']);

            Route::get('annees-scolaires', [AnneeScolaireController::class, 'index'])->name('annees.index')->middleware('permission:annees_scolaires.view');
            Route::post('annees-scolaires', [AnneeScolaireController::class, 'store'])->name('annees.store')->middleware('permission:annees_scolaires.create');
            Route::put('annees-scolaires/{id}', [AnneeScolaireController::class, 'update'])->name('annees.update')->middleware('permission:annees_scolaires.update');
            Route::post('annees-scolaires/{id}/activer', [AnneeScolaireController::class, 'activate'])->name('annees.activate')->middleware('permission:annees_scolaires.activer');
            Route::post('annees-scolaires/{id}/generer-seances', [AnneeScolaireController::class, 'genererSeances'])->name('annees.generer-seances')->middleware('permission:annees_scolaires.seances');
            Route::post('annees-scolaires/{id}/supprimer-seances', [AnneeScolaireController::class, 'supprimerSeances'])->name('annees.supprimer-seances')->middleware('permission:annees_scolaires.seances');
            Route::post('annees-scolaires/{id}/archiver', [AnneeScolaireController::class, 'archiver'])->name('annees.archiver')->middleware('permission:annees_scolaires.archiver');
            Route::post('annees-scolaires/{id}/basculer', [AnneeScolaireController::class, 'basculer'])->name('annees.basculer')->middleware('permission:annees_scolaires.archiver');

            Route::get('settings', [SettingController::class, 'index'])->name('settings.index')->middleware('permission:parametres.update');
            Route::put('settings', [SettingController::class, 'update'])->name('settings.update')->middleware('permission:parametres.update');

            Route::get('ecole', [SchoolController::class, 'show'])->name('ecole.show')->middleware('permission:ecoles.update');
            Route::put('ecole', [SchoolController::class, 'update'])->name('ecole.update')->middleware('permission:ecoles.update');
            Route::post('ecole/images/{type}', [SchoolController::class, 'uploadImage'])->name('ecole.images.upload')->middleware('permission:ecoles.update');
            Route::delete('ecole/images/{type}', [SchoolController::class, 'deleteImage'])->name('ecole.images.delete')->middleware('permission:ecoles.update');

            /*
             * Conseil de classe de fin d'année et archives des années
             * révolues — cf. ConseilClasseService/ArchivageService. Consulter
             * une archive ou un PV ne demande que `.view` ; mener un conseil
             * exige `.update`, le clore `.valider`.
             */
            Route::middleware('permission:conseil_classe.view')->group(function () {
                Route::get('classes/{classeId}/conseil', [ConseilClasseController::class, 'show'])->name('conseil-classe.show');
                Route::get('conseils-classe/{id}/pv', [ConseilClasseController::class, 'pv'])->name('conseil-classe.pv');
                Route::get('archives/annees', [ArchiveClasseController::class, 'annees'])->name('archives.annees');
                Route::get('archives/annees/{anneeId}/classes', [ArchiveClasseController::class, 'classes'])->name('archives.classes');
                Route::get('archives/annees/{anneeId}/classes/{classeId}', [ArchiveClasseController::class, 'show'])->name('archives.show');
                Route::get('archives/annees/{anneeId}/classes/{classeId}/bulletin/{eleveId}', [ArchiveClasseController::class, 'bulletin'])->name('archives.bulletin');
                Route::get('archives/annees/{anneeId}/classes/{classeId}/pv', [ArchiveClasseController::class, 'pv'])->name('archives.pv');
            });

            Route::put('conseils-classe/{id}/seuil', [ConseilClasseController::class, 'definirSeuil'])->name('conseil-classe.seuil')->middleware('permission:conseil_classe.update');
            Route::post('conseils-classe/{id}/destination', [ConseilClasseController::class, 'definirDestination'])->name('conseil-classe.destination')->middleware('permission:conseil_classe.update');
            Route::post('conseil-classe-decisions/{decisionId}/exclure', [ConseilClasseController::class, 'exclure'])->name('conseil-classe.decisions.exclure')->middleware('permission:conseil_classe.update');
            Route::post('conseil-classe-decisions/{decisionId}/gracier', [ConseilClasseController::class, 'gracier'])->name('conseil-classe.decisions.gracier')->middleware('permission:conseil_classe.update');
            Route::post('conseil-classe-decisions/{decisionId}/annuler-ajustement', [ConseilClasseController::class, 'annulerAjustement'])->name('conseil-classe.decisions.annuler')->middleware('permission:conseil_classe.update');
            Route::post('conseils-classe/{id}/valider', [ConseilClasseController::class, 'valider'])->name('conseil-classe.valider')->middleware('permission:conseil_classe.valider');

            /*
             * Responsabilités confiées au compte connecté (professeur
             * principal, surveillant général, censeur, conseiller
             * d'orientation, chef de département). Sans privilège requis : il
             * n'y lit que ce qui le concerne, et c'est précisément cette
             * réponse qui dit à l'interface quels écrans lui ouvrir.
             */
            Route::get('mes-attributions', [ClasseController::class, 'mesAttributions'])->name('classes.mes-attributions');

            Route::middleware('permission:classes.view')->group(function () {
                Route::get('classes', [ClasseController::class, 'index'])->name('classes.index');
                // Avant `classes/{id}` : sinon "export"/"modele" y seraient captés comme un identifiant.
                Route::get('classes/export', [ClasseController::class, 'export'])->name('classes.export');
                Route::get('classes/modele', [ClasseController::class, 'modele'])->name('classes.modele');
                Route::get('ma-classe', [ClasseController::class, 'maClasse'])->name('classes.ma-classe');
                Route::get('classes/{id}/cartes-scolaires', [CarteScolaireController::class, 'classe'])->name('classes.cartes');
                Route::get('classes/{id}/eleves/pdf', [ListeElevesController::class, 'pdf'])->name('classes.eleves.pdf');
                Route::get('classes/{id}/eleves/word', [ListeElevesController::class, 'word'])->name('classes.eleves.word');
                Route::get('classes/liste-personnalisee/modeles', [ListeClassePersonnaliseeController::class, 'modeles'])->name('classes.liste-personnalisee.modeles.index');
                Route::post('classes/liste-personnalisee/modeles', [ListeClassePersonnaliseeController::class, 'storeModele'])->name('classes.liste-personnalisee.modeles.store');
                Route::put('classes/liste-personnalisee/modeles/{id}', [ListeClassePersonnaliseeController::class, 'updateModele'])->name('classes.liste-personnalisee.modeles.update');
                Route::delete('classes/liste-personnalisee/modeles/{id}', [ListeClassePersonnaliseeController::class, 'destroyModele'])->name('classes.liste-personnalisee.modeles.destroy');
                Route::get('classes/{id}/liste-personnalisee/pdf', [ListeClassePersonnaliseeController::class, 'pdf'])->name('classes.liste-personnalisee.pdf');
                Route::get('classes/{id}/liste-personnalisee/word', [ListeClassePersonnaliseeController::class, 'word'])->name('classes.liste-personnalisee.word');
                Route::get('classes/{id}/liste-personnalisee/excel', [ListeClassePersonnaliseeController::class, 'excel'])->name('classes.liste-personnalisee.excel');
                Route::get('classes/{id}', [ClasseController::class, 'show'])->name('classes.show');
                Route::get('schools', [ClasseController::class, 'schools'])->name('schools.index');
                Route::get('schools/{id}', [ClasseController::class, 'showSchool'])->name('schools.show');
            });

            Route::post('classes/fusionner', [ClasseController::class, 'fusionner'])->name('classes.fusionner')->middleware('permission:classes.fusionner');
            Route::post('classes', [ClasseController::class, 'store'])->name('classes.store')->middleware('permission:classes.create');
            Route::post('classes/import', [ClasseController::class, 'import'])->name('classes.import')->middleware('permission:classes.import');
            Route::put('classes/bulk-update', [ClasseController::class, 'bulkUpdate'])->name('classes.bulk-update')->middleware('permission:classes.update');
            Route::post('classes/batch-delete', [ClasseController::class, 'batchDestroy'])->name('classes.batch-destroy')->middleware('permission:classes.delete');
            Route::put('classes/{id}', [ClasseController::class, 'update'])->name('classes.update')->middleware('permission:classes.update');
            Route::delete('classes/{id}', [ClasseController::class, 'destroy'])->name('classes.destroy')->middleware('permission:classes.delete');

            Route::get('sous-systemes', [SousSystemeController::class, 'index'])->name('sous-systemes.index')->middleware('permission:sous_systemes.view');
            Route::get('sous-systemes/export', [SousSystemeController::class, 'export'])->name('sous-systemes.export')->middleware('permission:sous_systemes.view');
            Route::get('sous-systemes/modele', [SousSystemeController::class, 'modele'])->name('sous-systemes.modele')->middleware('permission:sous_systemes.view');
            Route::post('sous-systemes/import', [SousSystemeController::class, 'import'])->name('sous-systemes.import')->middleware('permission:sous_systemes.import');
            Route::post('sous-systemes', [SousSystemeController::class, 'store'])->name('sous-systemes.store')->middleware('permission:sous_systemes.create');
            Route::get('sous-systemes/{id}', [SousSystemeController::class, 'show'])->name('sous-systemes.show')->middleware('permission:sous_systemes.view');
            Route::put('sous-systemes/{id}', [SousSystemeController::class, 'update'])->name('sous-systemes.update')->middleware('permission:sous_systemes.update');
            Route::delete('sous-systemes/{id}', [SousSystemeController::class, 'destroy'])->name('sous-systemes.destroy')->middleware('permission:sous_systemes.delete');

            Route::middleware('permission:eleves.view')->group(function () {
                // Photos DECC & OBC : réservées aux classes d'examen.
                Route::get('photos-examen/classes', [PhotoExamenController::class, 'classes'])->name('photos-examen.classes');
                Route::get('photos-examen/classes/{classeId}', [PhotoExamenController::class, 'candidats'])->name('photos-examen.candidats');
                Route::get('photos-examen/classes/{classeId}/archive', [PhotoExamenController::class, 'archive'])->name('photos-examen.archive');
                Route::get('eleves', [EleveController::class, 'index'])->name('eleves.index');
                Route::get('eleves/{id}/parcours', [EleveController::class, 'parcours'])->name('eleves.parcours');
                Route::get('eleves/recherche-globale', [EleveController::class, 'rechercheGlobale'])->name('eleves.recherche-globale');
                Route::get('eleves/repartition', [EleveController::class, 'repartition'])->name('eleves.repartition');
                Route::get('eleves/export', [EleveController::class, 'export'])->name('eleves.export');
                Route::get('eleves/modele', [EleveController::class, 'modele'])->name('eleves.modele');
                Route::get('eleves/pdf', [ListeElevesController::class, 'pdfEcole'])->name('eleves.pdf');
                Route::get('eleves/recapitulatif-effectifs', [EleveRapportsController::class, 'recapitulatif'])->name('eleves.recapitulatif');
                Route::get('eleves/recapitulatif-effectifs/pdf', [EleveRapportsController::class, 'recapitulatifPdf'])->name('eleves.recapitulatif.pdf');
                Route::get('eleves/recapitulatif-sous-systemes', [EleveRapportsController::class, 'recapitulatifSousSystemes'])->name('eleves.recapitulatif-ss');
                Route::get('eleves/recapitulatif-sous-systemes/pdf', [EleveRapportsController::class, 'recapitulatifSousSystemesPdf'])->name('eleves.recapitulatif-ss.pdf');
                Route::get('eleves/tableau-ages', [EleveRapportsController::class, 'tableauAges'])->name('eleves.tableau-ages');
                Route::get('eleves/tableau-ages/pdf', [EleveRapportsController::class, 'tableauAgesPdf'])->name('eleves.tableau-ages.pdf');
                Route::get('eleves/rapport-minorites', [EleveRapportsController::class, 'rapportMinorites'])->name('eleves.rapport-minorites');
                Route::get('eleves/{eleveId}/attestation-scolarite', [AttestationController::class, 'scolarite'])->name('eleves.attestation');
                // Avant `eleves/{id}` ci-dessous : sinon Laravel matche
                // `/eleves/doublons` comme show(id="doublons") en premier
                // (routes GET évaluées dans l'ordre de déclaration) et
                // plante avec un TypeError sur l'argument entier attendu.
                Route::get('eleves/doublons', [EleveController::class, 'doublons'])->name('eleves.doublons.index');
                Route::get('eleves/non-preinscrits-sans-historique', [EleveController::class, 'nonPreinscritsSansHistorique'])->name('eleves.non-preinscrits-sans-historique');
                Route::get('eleves/non-preinscrits-sans-historique/diagnostic', [EleveController::class, 'diagnosticNonPreinscritsSansHistorique'])->name('eleves.non-preinscrits-sans-historique.diagnostic');
                Route::get('eleves/{id}', [EleveController::class, 'show'])->name('eleves.show');
                Route::get('matricule-national/recherche', [MatriculeNationalController::class, 'rechercher'])->name('matricule-national.recherche');
                // Routes statiques déclarées avant `matricules-nationaux/{id}`
                // (ajoutée avec les écritures sur les élèves ci-dessous) : même
                // précaution que pour `eleves/doublons` juste au-dessus.
                Route::get('matricules-nationaux', [MatriculeNationalController::class, 'index'])->name('matricules-nationaux.index');
                Route::get('matricules-nationaux/export', [MatriculeNationalController::class, 'export'])->name('matricules-nationaux.export');
                Route::get('matricules-nationaux/modele', [MatriculeNationalController::class, 'modele'])->name('matricules-nationaux.modele');
            });

            Route::post('eleves/doublons/traitement-automatique', [EleveController::class, 'traitementAutomatiqueDoublons'])->name('eleves.doublons.automatique')->middleware('permission:eleves.fusionner');
            Route::post('eleves/doublons/fusionner', [EleveController::class, 'fusionnerDoublon'])->name('eleves.doublons.fusionner')->middleware('permission:eleves.fusionner');
            Route::post('eleves', [EleveController::class, 'store'])->name('eleves.store')->middleware('permission:eleves.create');
            Route::put('eleves/{id}', [EleveController::class, 'update'])->name('eleves.update')->middleware('permission:eleves.update');
            // Avant `eleves/{id}` juste en dessous : même précaution que pour
            // `eleves/doublons` plus haut, appliquée cette fois au verbe DELETE.
            Route::delete('eleves/non-preinscrits-sans-historique', [EleveController::class, 'supprimerNonPreinscritsSansHistorique'])->name('eleves.non-preinscrits-sans-historique.destroy')->middleware('permission:eleves.delete');
            Route::delete('eleves/{id}', [EleveController::class, 'destroy'])->name('eleves.destroy')->middleware('permission:eleves.delete');
            Route::post('eleves/batch-delete', [EleveController::class, 'batchDelete'])->name('eleves.batch-delete')->middleware('permission:eleves.delete');
            Route::post('eleves/normaliser-matricules', [EleveController::class, 'normaliserMatricules'])->name('eleves.normaliser-matricules')->middleware('permission:eleves.update');
            Route::post('eleves/batch-transfert-classe', [EleveController::class, 'batchTransfertClasse'])->name('eleves.batch-transfert-classe')->middleware('permission:eleves.transferer');
            Route::post('matricules-nationaux/import', [MatriculeNationalController::class, 'import'])->name('matricules-nationaux.import')->middleware('permission:matricules_nationaux.import');
            Route::put('matricules-nationaux/{id}', [MatriculeNationalController::class, 'update'])->name('matricules-nationaux.update')->middleware('permission:matricules_nationaux.update');
            Route::post('eleves/batch-transfert-ecole', [EleveController::class, 'batchTransfertEcole'])->name('eleves.batch-transfert-ecole')->middleware('permission:eleves.transferer');
            Route::post('eleves/import', [EleveController::class, 'import'])->name('eleves.import')->middleware('permission:eleves.import');
            Route::get('eleves/import-progress/{token}', [EleveController::class, 'importProgress'])->name('eleves.import-progress')->middleware('permission:eleves.import');
            Route::post('eleves/import/preparer', [EleveController::class, 'importPreparer'])->name('eleves.import-preparer')->middleware('permission:eleves.import');
            Route::post('eleves/import/traiter/{token}', [EleveController::class, 'importerLot'])->name('eleves.import-traiter')->middleware('permission:eleves.import');
            Route::post('eleves/{id}/transfert', [EleveController::class, 'transfert'])->name('eleves.transfert')->middleware('permission:eleves.transferer');
            Route::post('eleves/{id}/photo', [EleveController::class, 'photo'])->name('eleves.photo')->middleware('permission:eleves.update');
            Route::delete('eleves/{id}/photo', [EleveController::class, 'supprimerPhoto'])->name('eleves.photo.destroy')->middleware('permission:eleves.update');

            Route::get('tuteurs', [TuteurController::class, 'index'])->name('tuteurs.index')->middleware('permission:tuteurs.view');
            // Avant `tuteurs/{id}/...` plus bas, même précaution que pour
            // `eleves/doublons` : un segment statique se déclare avant tout
            // pattern dynamique susceptible de le capturer à sa place.
            Route::get('tuteurs/doublons', [TuteurController::class, 'doublons'])->name('tuteurs.doublons.index')->middleware('permission:tuteurs.view');
            Route::post('tuteurs/doublons/fusionner', [TuteurController::class, 'fusionnerDoublon'])->name('tuteurs.doublons.fusionner')->middleware('permission:tuteurs.fusionner');
            Route::get('tuteurs/recherche', [TuteurController::class, 'recherche'])->name('tuteurs.recherche')->middleware('permission:tuteurs.view|eleves.create|eleves.update|preinscriptions.create');
            Route::get('tuteurs/identifiants/pdf', [TuteurController::class, 'identifiantsParentPdf'])->name('tuteurs.identifiants-pdf')->middleware('permission:tuteurs.comptes');
            Route::post('tuteurs/{id}/compte-parent', [TuteurController::class, 'creerCompteParent'])->name('tuteurs.compte-parent')->middleware('permission:tuteurs.comptes');
            Route::post('tuteurs/{id}/basculer-acces', [TuteurController::class, 'basculerAcces'])->name('tuteurs.basculer-acces')->middleware('permission:tuteurs.comptes');
            Route::post('tuteurs/{id}/reinitialiser-mot-de-passe', [TuteurController::class, 'reinitialiserMotDePasse'])->name('tuteurs.reinitialiser-mot-de-passe')->middleware('permission:tuteurs.comptes');
            Route::post('tuteurs/{id}/enfants', [TuteurController::class, 'rattacherEnfants'])->name('tuteurs.rattacher-enfants')->middleware('permission:tuteurs.update');
            Route::delete('tuteurs/{id}/enfants/{eleveId}', [TuteurController::class, 'detacherEnfant'])->name('tuteurs.detacher-enfant')->middleware('permission:tuteurs.update');
            Route::delete('tuteurs/{id}/compte-parent', [TuteurController::class, 'supprimerCompteParent'])->name('tuteurs.supprimer-compte-parent')->middleware('permission:tuteurs.comptes');
            Route::post('tuteurs/{id}/supprimer-compte-parent', [TuteurController::class, 'supprimerCompteParent'])->name('tuteurs.supprimer-compte-parent-post')->middleware('permission:tuteurs.comptes');
            Route::post('tuteurs/comptes-parent-lot', [TuteurController::class, 'creerComptesParentLot'])->name('tuteurs.comptes-parent-lot')->middleware('permission:tuteurs.comptes');
            Route::post('tuteurs/comptes-parent-lot/preparer', [TuteurController::class, 'comptesParentLotPreparer'])->name('tuteurs.comptes-parent-lot-preparer')->middleware('permission:tuteurs.comptes');
            Route::post('tuteurs/comptes-parent-lot/traiter', [TuteurController::class, 'comptesParentLotTraiter'])->name('tuteurs.comptes-parent-lot-traiter')->middleware('permission:tuteurs.comptes');
            // Fiche du tuteur depuis l'écran des comptes parents (nom,
            // profession, adresse, contact, e-mail).
            Route::put('tuteurs/{id}', [TuteurController::class, 'update'])->name('tuteurs.update')->middleware('permission:tuteurs.update');
            Route::delete('tuteurs/{id}', [TuteurController::class, 'destroy'])->name('tuteurs.destroy')->middleware('permission:tuteurs.delete');
            Route::get('parent-usage-stats', [ParentUsageStatsController::class, 'index'])->name('parent-usage-stats.index')->middleware('permission:tuteurs.view');
            Route::get('parent-usage-stats/comptes', [ParentUsageStatsController::class, 'comptes'])->name('parent-usage-stats.comptes')->middleware('permission:tuteurs.view');

            // Comptes du portail élève — même quatuor d'actions que les
            // comptes parent ci-dessus (cf. TuteurController), porté par
            // EleveController plutôt qu'un contrôleur dédié : ce sont des
            // actions sur la fiche élève, pas un domaine à part.
            Route::get('eleves/identifiants/pdf', [EleveController::class, 'identifiantsElevePdf'])->name('eleves.identifiants-pdf')->middleware('permission:eleves.comptes');
            Route::post('eleves/{id}/compte-eleve', [EleveController::class, 'creerCompteEleve'])->name('eleves.compte-eleve')->middleware('permission:eleves.comptes');
            Route::post('eleves/{id}/basculer-acces', [EleveController::class, 'basculerAcces'])->name('eleves.basculer-acces')->middleware('permission:eleves.comptes');
            Route::delete('eleves/{id}/compte-eleve', [EleveController::class, 'supprimerCompteEleve'])->name('eleves.supprimer-compte-eleve')->middleware('permission:eleves.comptes');
            Route::post('eleves/comptes-eleve-lot', [EleveController::class, 'creerComptesEleveLot'])->name('eleves.comptes-eleve-lot')->middleware('permission:eleves.comptes');

            Route::get('preinscriptions', [PreinscriptionAdminController::class, 'index'])->name('preinscriptions.index')->middleware('permission:preinscriptions.view');
            Route::post('preinscriptions', [PreinscriptionAdminController::class, 'store'])->name('preinscriptions.store')->middleware('permission:preinscriptions.create');
            Route::post('preinscriptions/nouveau', [PreinscriptionAdminController::class, 'storeNouveau'])->name('preinscriptions.nouveau')->middleware('permission:preinscriptions.create');
            Route::get('preinscriptions/{id}/recu', [PreinscriptionAdminController::class, 'recu'])->name('preinscriptions.recu')->middleware('permission:preinscriptions.view');
            // Routes statiques déclarées avant `preinscriptions/{id}` : sinon
            // Laravel les fait matcher par le paramètre `{id}` (ex.
            // "export" essaierait de charger la préinscription n°"export").
            Route::get('database/schema', [PreinscriptionAdminController::class, 'schema'])->name('database.schema')->middleware('permission:preinscriptions.view');
            Route::get('database/migrations', [PreinscriptionAdminController::class, 'migrations'])->name('database.migrations')->middleware('permission:preinscriptions.view');
            Route::get('preinscriptions/non-inscrits', [PreinscriptionAdminController::class, 'nonInscrits'])->name('preinscriptions.non-inscrits')->middleware('permission:preinscriptions.view');
            Route::get('preinscriptions/export', [PreinscriptionAdminController::class, 'export'])->name('preinscriptions.export')->middleware('permission:preinscriptions.view');
            Route::get('preinscriptions/non-inscrits/export', [PreinscriptionAdminController::class, 'exportNonInscrits'])->name('preinscriptions.non-inscrits.export')->middleware('permission:preinscriptions.view');
            Route::get('preinscriptions/modele', [PreinscriptionAdminController::class, 'modele'])->name('preinscriptions.modele')->middleware('permission:preinscriptions.view');
            Route::post('preinscriptions/import', [PreinscriptionAdminController::class, 'import'])->name('preinscriptions.import')->middleware('permission:preinscriptions.import');
            Route::post('preinscriptions/import/preparer', [PreinscriptionAdminController::class, 'importPreparer'])->name('preinscriptions.import-preparer')->middleware('permission:preinscriptions.import');
            Route::post('preinscriptions/import/traiter/{token}', [PreinscriptionAdminController::class, 'importerLot'])->name('preinscriptions.import-traiter')->middleware('permission:preinscriptions.import');
            Route::post('preinscriptions/bulk-valider', [PreinscriptionAdminController::class, 'validerEnMasse'])->name('preinscriptions.bulk-valider')->middleware('permission:preinscriptions.valider');
            Route::post('preinscriptions/bulk-rejeter', [PreinscriptionAdminController::class, 'rejeterEnMasse'])->name('preinscriptions.bulk-rejeter')->middleware('permission:preinscriptions.valider');
            Route::get('preinscriptions/{id}', [PreinscriptionAdminController::class, 'show'])->name('preinscriptions.show')->middleware('permission:preinscriptions.view');
            Route::put('preinscriptions/{id}', [PreinscriptionAdminController::class, 'update'])->name('preinscriptions.update')->middleware('permission:preinscriptions.update');
            Route::post('preinscriptions/{id}/valider', [PreinscriptionAdminController::class, 'valider'])->name('preinscriptions.valider')->middleware('permission:preinscriptions.valider');
            Route::post('preinscriptions/{id}/rejeter', [PreinscriptionAdminController::class, 'rejeter'])->name('preinscriptions.rejeter')->middleware('permission:preinscriptions.valider');
            Route::delete('preinscriptions/{id}', [PreinscriptionAdminController::class, 'supprimer'])->name('preinscriptions.supprimer')->middleware('permission:preinscriptions.delete');

            Route::get('modifications-eleves', [ModificationEleveAdminController::class, 'index'])->name('modifications-eleves.index')->middleware('permission:modifications_eleves.view');
            Route::get('modifications-eleves/{id}', [ModificationEleveAdminController::class, 'show'])->name('modifications-eleves.show')->middleware('permission:modifications_eleves.view');
            Route::post('modifications-eleves/{id}/valider', [ModificationEleveAdminController::class, 'valider'])->name('modifications-eleves.valider')->middleware('permission:modifications_eleves.valider');
            Route::post('modifications-eleves/{id}/rejeter', [ModificationEleveAdminController::class, 'rejeter'])->name('modifications-eleves.rejeter')->middleware('permission:modifications_eleves.valider');

            Route::get('justifications', [JustificationAbsenceAdminController::class, 'index'])->name('justifications.index')->middleware('permission:justifications.view');
            Route::get('justifications/{id}', [JustificationAbsenceAdminController::class, 'show'])->name('justifications.show')->middleware('permission:justifications.view');

            Route::get('observations', [ObservationAdminController::class, 'index'])->name('observations.index')->middleware('permission:observations.view');
            Route::get('observations/{eleveId}', [ObservationAdminController::class, 'show'])->name('observations.show')->middleware('permission:observations.view');
            Route::post('observations/{eleveId}', [ObservationAdminController::class, 'repondre'])->name('observations.repondre')->middleware('permission:observations.repondre');

            /*
             * Portail parent. Gardé par le rôle et non par un privilège
             * `X.view` : ces routes ne rendent jamais qu'un sous-ensemble
             * borné aux propres enfants du compte (cf. `ParentAccess`), pas
             * une vue école entière — un privilège de lecture n'y aurait pas
             * le même sens que pour le personnel.
             */
            Route::prefix('parent')->name('parent.')->middleware('role:parent')->group(function () {
                Route::get('champs-manquants', [ParentEspaceController::class, 'champsManquants'])->name('champs-manquants');
                Route::post('tuteur/completer', [ParentEspaceController::class, 'completerTuteur'])->name('tuteur.completer');

                Route::get('enfants', [ParentEspaceController::class, 'mesEnfants'])->name('enfants.index');
                Route::get('enfants/{eleveId}', [ParentEspaceController::class, 'enfant'])->name('enfants.show');
                Route::post('enfants/{eleveId}/completer', [ParentEspaceController::class, 'completerEnfant'])->name('enfants.completer');
                Route::post('enfants/{eleveId}/completer/photo', [ParentEspaceController::class, 'completerPhotoEnfant'])->name('enfants.completer.photo');
                Route::get('enfants/{eleveId}/finance', [ParentEspaceController::class, 'finance'])->name('enfants.finance');
                Route::get('enfants/{eleveId}/bulletin', [ParentEspaceController::class, 'bulletin'])->name('enfants.bulletin');
                Route::get('enfants/{eleveId}/progression', [ParentEspaceController::class, 'progression'])->name('enfants.progression');
                Route::get('enfants/{eleveId}/progression/{classeMatiereId}', [ParentEspaceController::class, 'progressionMatiere'])->name('enfants.progression.show');
                Route::get('enfants/{eleveId}/lecons-semaine', [ParentEspaceController::class, 'leconsSemaine'])->name('enfants.lecons-semaine');
                Route::get('enfants/{eleveId}/absences', [ParentEspaceController::class, 'absences'])->name('enfants.absences');
                Route::get('enfants/{eleveId}/assiduite', [ParentEspaceController::class, 'assiduite'])->name('enfants.assiduite');
                Route::get('enfants/{eleveId}/emploi-du-temps', [ParentEspaceController::class, 'emploiDuTemps'])->name('enfants.emploi-du-temps');
                Route::get('enfants/{eleveId}/emploi-du-temps/pdf', [ParentEspaceController::class, 'emploiDuTempsPdf'])->name('enfants.emploi-du-temps.pdf');
                Route::get('enfants/{eleveId}/visites-infirmerie', [ParentEspaceController::class, 'visitesInfirmerie'])->name('enfants.visites-infirmerie');
                Route::get('enfants/{eleveId}/sanctions', [ParentEspaceController::class, 'sanctions'])->name('enfants.sanctions');
                Route::get('enfants/{eleveId}/justifications', [ParentEspaceController::class, 'justifications'])->name('enfants.justifications.index');
                Route::post('enfants/{eleveId}/justifications', [ParentEspaceController::class, 'soumettreJustification'])->name('enfants.justifications.store');
                Route::get('enfants/{eleveId}/observations', [ParentEspaceController::class, 'observations'])->name('enfants.observations.index');
                Route::post('enfants/{eleveId}/observations', [ParentEspaceController::class, 'soumettreObservation'])->name('enfants.observations.store');
                Route::get('enfants/{eleveId}/modification', [ParentEspaceController::class, 'modificationEnAttente'])->name('enfants.modification.show');
                Route::post('enfants/{eleveId}/modification', [ParentEspaceController::class, 'soumettreModification'])->name('enfants.modification.store');
                Route::get('enfants/{eleveId}/modifications', [ParentEspaceController::class, 'historiqueModifications'])->name('enfants.modifications.index');

                Route::get('preinscriptions', [ParentPreinscriptionController::class, 'index'])->name('preinscriptions.index');
                Route::post('preinscriptions', [ParentPreinscriptionController::class, 'store'])->name('preinscriptions.store');
                Route::get('preinscriptions/{id}', [ParentPreinscriptionController::class, 'show'])->name('preinscriptions.show');
                Route::put('preinscriptions/{id}', [ParentPreinscriptionController::class, 'update'])->name('preinscriptions.update');
                Route::get('ecoles-disponibles', [ParentPreinscriptionController::class, 'ecolesDisponibles'])->name('ecoles-disponibles');
                Route::get('ecoles/{schoolId}/classes', [ParentPreinscriptionController::class, 'classesDisponibles'])->name('ecoles.classes');

                Route::get('bibliotheque', [ParentEspaceController::class, 'bibliotheque'])->name('bibliotheque.index');
            });

            /**
             * Portail élève. Gardé par le rôle, comme le portail parent — ces
             * routes ne rendent jamais que la fiche du compte connecté (cf.
             * `EleveAccess`), en lecture seule, sans le volet finance (réservé
             * au tuteur).
             */
            Route::prefix('eleve')->name('eleve.')->middleware('role:eleve')->group(function () {
                Route::get('moi', [EleveEspaceController::class, 'moi'])->name('moi');
                Route::get('notes', [EleveEspaceController::class, 'notes'])->name('notes');
                Route::get('bulletin', [EleveEspaceController::class, 'bulletin'])->name('bulletin');
                Route::get('emploi-du-temps', [EleveEspaceController::class, 'emploiDuTemps'])->name('emploi-du-temps');
                Route::get('visites-infirmerie', [EleveEspaceController::class, 'visitesInfirmerie'])->name('visites-infirmerie');
                Route::get('sanctions', [EleveEspaceController::class, 'sanctions'])->name('sanctions');
                Route::get('absences', [EleveEspaceController::class, 'absences'])->name('absences');
                Route::get('assiduite', [EleveEspaceController::class, 'assiduite'])->name('assiduite');
            });

            /*
             * Espace personnel : libre-service pour l'employé sur ses propres
             * avances — aucun rôle dédié, juste la présence d'une fiche
             * Personnel liée au compte (cf. PersonnelEspaceController::moi()).
             * Pas de middleware permission/role ici, volontairement : c'est un
             * périmètre "moi-même", pas un privilège de gestion.
             */
            Route::prefix('mon-espace')->name('mon-espace.')->group(function () {
                Route::get('avances', [PersonnelEspaceController::class, 'mesAvances'])->name('avances.index');
                Route::post('avances/demandes', [PersonnelEspaceController::class, 'soumettreDemandeAvance'])->name('avances.demandes.store');
                Route::get('inventaire/demandes', [PersonnelEspaceController::class, 'mesDemandesArticles'])->name('inventaire.demandes.index');
                Route::post('inventaire/demandes', [PersonnelEspaceController::class, 'soumettreDemandeArticle'])->name('inventaire.demandes.store');
                Route::get('budgets', [PersonnelEspaceController::class, 'mesBudgets'])->name('budgets.index');
                Route::put('budgets/{id}/note-gestion', [PersonnelEspaceController::class, 'modifierNoteGestionBudget'])->name('budgets.note-gestion');
                Route::get('budgets/{id}/bilan/pdf', [PersonnelEspaceController::class, 'bilanBudgetPdf'])->name('budgets.bilan-pdf');
                Route::get('bibliotheque', [PersonnelEspaceController::class, 'bibliotheque'])->name('bibliotheque.index');
            });

            /*
             * Espace enseignant : même principe que « mon-espace » ci-dessus,
             * étendu à la fiche personnel, la rémunération en lecture seule, et
             * à l'unique geste de gestion qu'un enseignant garde sur sa fiche de
             * progression (ajouter une évaluation) quand `evaluations.create` ne
             * lui est pas accordé. Le périmètre est vérifié dans le contrôleur,
             * pas ici : ce sont des routes sans `{classeId}` à borner.
             */
            Route::prefix('enseignant')->name('enseignant.')->group(function () {
                Route::get('mes-informations', [EnseignantController::class, 'mesInformations'])->name('mes-informations.show');
                Route::put('mes-informations', [EnseignantController::class, 'mettreAJourMesInformations'])->name('mes-informations.update');
                Route::get('remuneration', [EnseignantController::class, 'maRemuneration'])->name('remuneration.show');
                Route::get('mon-departement', [EnseignantController::class, 'monDepartement'])->name('mon-departement.show');
                Route::get('ma-classe-prof-principal', [EnseignantController::class, 'maClasseProfPrincipal'])->name('ma-classe-prof-principal.show');
                Route::get('mon-niveau', [EnseignantController::class, 'monNiveau'])->name('mon-niveau.show');
                Route::post('classe-matieres/{classeMatiereId}/evaluations', [EnseignantController::class, 'ajouterEvaluation'])->name('evaluations.store');
            });

            Route::middleware('permission:notes.view')->group(function () {
                Route::get('matieres', [MatiereController::class, 'index'])->name('matieres.index');

                /*
                 * Compétences évaluées du primaire et de la maternelle : le
                 * référentiel qui porte les barèmes, et son attribution aux
                 * classes. Les matières en découlent.
                 */
                Route::get('competences', [CompetenceController::class, 'index'])->name('competences.index');
                Route::get('competences/export', [CompetenceController::class, 'export'])->name('competences.export');
                Route::get('competences/modele', [CompetenceController::class, 'modele'])->name('competences.modele');
                // Référentiel d'appréciations de la maternelle : les niveaux
                // cochés à la saisie et coloriés sur le bulletin.
                Route::get('appreciations', [AppreciationController::class, 'index'])->name('appreciations.index');
                Route::get('appreciations/export', [AppreciationController::class, 'export'])->name('appreciations.export');
                Route::get('appreciations/modele', [AppreciationController::class, 'modele'])->name('appreciations.modele');
                Route::get('classes/{classeId}/competences', [CompetenceController::class, 'parClasse'])->name('classes.competences.index');
                Route::get('matieres/export', [MatiereController::class, 'export'])->name('matieres.export');
                Route::get('matieres/{id}/classes', [MatiereController::class, 'classes'])->name('matieres.classes');
                Route::get('classes/{classeId}/matieres', [ClasseMatiereController::class, 'index'])->name('classes.matieres.index');
            });

            Route::post('appreciations/import', [AppreciationController::class, 'import'])->name('appreciations.import')->middleware('permission:appreciations.import');
            Route::post('appreciations', [AppreciationController::class, 'store'])->name('appreciations.store')->middleware('permission:appreciations.create');
            Route::put('appreciations/{id}', [AppreciationController::class, 'update'])->name('appreciations.update')->middleware('permission:appreciations.update');
            Route::delete('appreciations/{id}', [AppreciationController::class, 'destroy'])->name('appreciations.destroy')->middleware('permission:appreciations.delete');

            Route::post('competences/import', [CompetenceController::class, 'import'])->name('competences.import')->middleware('permission:competences.import');
            Route::post('competences', [CompetenceController::class, 'store'])->name('competences.store')->middleware('permission:competences.create');
            Route::put('competences/{id}', [CompetenceController::class, 'update'])->name('competences.update')->middleware('permission:competences.update');
            Route::delete('competences/{id}', [CompetenceController::class, 'destroy'])->name('competences.destroy')->middleware('permission:competences.delete');
            Route::post('competences/batch-delete', [CompetenceController::class, 'batchDestroy'])->name('competences.batch-delete')->middleware('permission:competences.delete');
            Route::post('classes/{classeId}/competences', [CompetenceController::class, 'attribuer'])->name('classes.competences.attribuer')->middleware('permission:competences.attribuer');
            Route::put('classe-competences/{id}', [CompetenceController::class, 'modifierAttribution'])->name('classe-competences.update')->middleware('permission:competences.attribuer');
            Route::delete('classe-competences/{id}', [CompetenceController::class, 'retirer'])->name('classe-competences.destroy')->middleware('permission:competences.attribuer');
            Route::post('classe-competences/copier', [CompetenceController::class, 'copier'])->name('classe-competences.copier')->middleware('permission:competences.attribuer');
            Route::post('classe-competences/batch-delete', [CompetenceController::class, 'batchRetirer'])->name('classe-competences.batch-delete')->middleware('permission:competences.attribuer');

            Route::post('matieres', [MatiereController::class, 'store'])->name('matieres.store')->middleware('permission:matieres.create');
            Route::put('matieres/{id}', [MatiereController::class, 'update'])->name('matieres.update')->middleware('permission:matieres.update');
            Route::delete('matieres/{id}', [MatiereController::class, 'destroy'])->name('matieres.destroy')->middleware('permission:matieres.delete');
            Route::post('matieres/batch-delete', [MatiereController::class, 'batchDestroy'])->name('matieres.batch-destroy')->middleware('permission:matieres.delete');
            Route::post('matieres/batch-competence', [MatiereController::class, 'batchCompetence'])->name('matieres.batch-competence')->middleware('permission:matieres.update');
            Route::post('matieres/fusionner', [MatiereController::class, 'fusionner'])->name('matieres.fusionner')->middleware('permission:matieres.fusionner');
            Route::post('matieres/import', [MatiereController::class, 'import'])->name('matieres.import')->middleware('permission:matieres.import');

            Route::post('classes/{classeId}/matieres', [ClasseMatiereController::class, 'store'])->name('classes.matieres.store')->middleware('permission:affectations.create');
            Route::put('classe-matieres/{id}', [ClasseMatiereController::class, 'update'])->name('classe-matieres.update')->middleware('permission:affectations.update');
            Route::delete('classe-matieres/{id}', [ClasseMatiereController::class, 'destroy'])->name('classe-matieres.destroy')->middleware('permission:affectations.delete');
            Route::post('classe-matieres/copier', [ClasseMatiereController::class, 'copier'])->name('classe-matieres.copier')->middleware('permission:affectations.create');
            Route::post('classe-matieres/batch-enseignant', [ClasseMatiereController::class, 'batchEnseignant'])->name('classe-matieres.batch-enseignant')->middleware('permission:affectations.update');

            /*
             * Progression pédagogique : le programme annuel se consulte avec la
             * pédagogie et ne s'édite qu'avec le droit de la gérer.
             */
            Route::middleware('permission:notes.view')->group(function () {
                Route::get('progression', [ProgressionController::class, 'etablissement'])->name('progression.etablissement');
                Route::get('classes/{classeId}/progression', [ProgressionController::class, 'classe'])->name('progression.classe');
                Route::get('classes/{classeId}/progression/pdf', [ProgressionController::class, 'pdfClasse'])->name('progression.classe-pdf');
                Route::get('classes/{classeId}/progression/modele', [ProgressionController::class, 'modeleClasse'])->name('progression.modele-classe');
                Route::get('classe-matieres/{classeMatiereId}/progression', [ProgressionController::class, 'show'])->name('progression.show');
                Route::get('classe-matieres/{classeMatiereId}/progression/pdf', [ProgressionController::class, 'pdf'])->name('progression.pdf');
                Route::get('classe-matieres/{classeMatiereId}/progression-colonnes', [ProgressionController::class, 'colonnes'])->name('progression-colonnes.index');
                Route::get('classe-matieres/{classeMatiereId}/champs-personnalises', [ProgressionController::class, 'champs'])->name('champs-personnalises.index');
                Route::get('classe-matieres/{classeMatiereId}/evaluations', [EvaluationController::class, 'index'])->name('evaluations.index');
            });

            Route::post('calendrier-scolaire', [CalendrierScolaireController::class, 'store'])->name('calendrier-scolaire.store')->middleware('permission:calendrier_scolaire.create');
            Route::delete('calendrier-scolaire/{id}', [CalendrierScolaireController::class, 'destroy'])->name('calendrier-scolaire.destroy')->middleware('permission:calendrier_scolaire.delete');
            Route::post('calendrier-scolaire/recalculer', [CalendrierScolaireController::class, 'recalculer'])->name('calendrier-scolaire.recalculer')->middleware('permission:calendrier_scolaire.create');
            Route::put('classe-matieres/{classeMatiereId}/progression', [ProgressionController::class, 'save'])->name('progression.save')->middleware('permission:progression.update');
            Route::post('classe-matieres/{classeMatiereId}/progression/import', [ProgressionController::class, 'import'])->name('progression.import')->middleware('permission:progression.import');
            Route::post('classes/{classeId}/progression/import', [ProgressionController::class, 'importClasse'])->name('progression.import-classe')->middleware('permission:progression.import');
            Route::put('classe-matieres/{classeMatiereId}/progression/cartouche', [ProgressionController::class, 'enregistrerCartouche'])->name('progression.cartouche')->middleware('permission:progression.update');
            Route::put('classe-matieres/{classeMatiereId}/progression-colonnes', [ProgressionController::class, 'enregistrerColonnes'])->name('progression-colonnes.save')->middleware('permission:progression.update');
            Route::put('classe-matieres/{classeMatiereId}/champs-personnalises', [ProgressionController::class, 'enregistrerChamps'])->name('champs-personnalises.save')->middleware('permission:progression.update');
            Route::post('classe-matieres/{classeMatiereId}/evaluations', [EvaluationController::class, 'store'])->name('evaluations.store')->middleware('permission:evaluations.create');
            Route::put('evaluations/{id}', [EvaluationController::class, 'update'])->name('evaluations.update')->middleware('permission:evaluations.update');
            Route::delete('evaluations/{id}', [EvaluationController::class, 'destroy'])->name('evaluations.destroy')->middleware('permission:evaluations.delete');

            Route::get('calendrier-scolaire', [CalendrierScolaireController::class, 'index'])
                ->name('calendrier-scolaire.index')->middleware('permission:pedagogie.view');
            Route::get('calendrier-scolaire/{date}', [CalendrierScolaireController::class, 'jour'])
                ->name('calendrier-scolaire.jour')->middleware('permission:pedagogie.view');

            /*
             * « Ma journée » : déclarer les leçons traitées et faire l'appel.
             * Ouvert à qui peut pointer une classe — l'enseignant y est en outre
             * restreint à ses propres affectations par le service. Les routes
             * littérales (couverture, qr/{token}) précèdent le paramètre
             * générique {classeMatiereId} pour ne pas s'y faire happer.
             */
            // Vue globale de la journée, réservée à l'administration — même
            // permission que « Suivi d'activité », que l'enseignant ordinaire
            // ne porte pas (contrairement à `appel.saisir`, ci-dessous, qui
            // couvre aussi son propre `ma-journee`). Route littérale avant
            // {classeMatiereId} du groupe suivant, pour ne pas s'y faire happer.
            Route::middleware('permission:personnel.view')->group(function () {
                Route::get('ma-journee/ecole', [MaJourneeController::class, 'ecole'])->name('ma-journee.ecole');
            });

            Route::middleware('permission:appel.saisir')->group(function () {
                Route::get('ma-journee/couverture', [MaJourneeController::class, 'couverture'])->name('ma-journee.couverture');
                Route::get('ma-journee/couverture-periodes', [MaJourneeController::class, 'couverturePeriodes'])->name('ma-journee.couverture-periodes');
                Route::get('ma-journee/qr/{token}', [MaJourneeController::class, 'resoudreQr'])->name('ma-journee.qr');
                Route::get('ma-journee', [MaJourneeController::class, 'affectations'])->name('ma-journee.affectations');
                Route::get('ma-journee/{classeMatiereId}', [MaJourneeController::class, 'feuille'])
                    ->whereNumber('classeMatiereId')->name('ma-journee.feuille');

                Route::get('ma-journee/{classeMatiereId}/lecons/{leconId}', [MaJourneeController::class, 'lecon'])
                    ->whereNumber(['classeMatiereId', 'leconId'])->name('ma-journee.lecon');

                Route::post('ma-journee/{classeMatiereId}', [MaJourneeController::class, 'enregistrer'])
                    ->whereNumber('classeMatiereId')->name('ma-journee.enregistrer');
            });

            Route::middleware('permission:pedagogie.view')->group(function () {
                Route::get('trimestres', [TrimestreController::class, 'index'])->name('trimestres.index');
            });

            Route::post('trimestres', [TrimestreController::class, 'store'])->name('trimestres.store')->middleware('permission:trimestres.create');
            Route::put('trimestres/{id}', [TrimestreController::class, 'update'])->name('trimestres.update')->middleware('permission:trimestres.update');
            Route::post('trimestres/{id}/activer', [TrimestreController::class, 'activate'])->name('trimestres.activate')->middleware('permission:trimestres.activer');
            Route::post('trimestres/{id}/generer-seances', [TrimestreController::class, 'genererSeances'])->name('trimestres.generer-seances')->middleware('permission:trimestres.seances');
            Route::post('trimestres/{id}/supprimer-seances', [TrimestreController::class, 'supprimerSeances'])->name('trimestres.supprimer-seances')->middleware('permission:trimestres.seances');

            Route::middleware('permission:notes.view')->group(function () {
                Route::get('classe-matieres/{classeMatiereId}/notes', [NoteController::class, 'index'])->name('notes.index');
            });

            Route::middleware('permission:notes.create')->group(function () {
                Route::post('classe-matieres/{classeMatiereId}/notes', [NoteController::class, 'bulkStore'])->name('notes.bulk-store');
                Route::post('classe-matieres/{classeMatiereId}/notes/import', [NoteController::class, 'import'])->name('notes.import');
            });

            /*
             * Primaire et maternelle — pédagogie propre à ces cycles : niveaux
             * d'enseignement animés par un responsable, saisie des notes par
             * volets d'évaluation et bulletins au format archange. Les
             * permissions restent celles du secondaire.
             */
            Route::middleware('permission:pedagogie.view')->group(function () {
                Route::get('niveaux-scolaires', [NiveauScolaireController::class, 'index'])->name('niveaux-scolaires.index');
            });

            Route::post('niveaux-scolaires', [NiveauScolaireController::class, 'store'])->name('niveaux-scolaires.store')->middleware('permission:niveaux_scolaires.create');
            Route::put('niveaux-scolaires/{id}', [NiveauScolaireController::class, 'update'])->name('niveaux-scolaires.update')->middleware('permission:niveaux_scolaires.update');
            Route::delete('niveaux-scolaires/{id}', [NiveauScolaireController::class, 'destroy'])->name('niveaux-scolaires.destroy')->middleware('permission:niveaux_scolaires.delete');

            Route::middleware('permission:notes.view')->group(function () {
                Route::get('classe-competences/{classeCompetenceId}/notes-primaire', [NotePrimaireController::class, 'index'])->name('notes-primaire.index');
            });

            Route::middleware('permission:notes.create')->group(function () {
                Route::post('classe-competences/{classeCompetenceId}/notes-primaire', [NotePrimaireController::class, 'bulkStore'])->name('notes-primaire.bulk-store');
            });

            Route::middleware('permission:notes.view')->group(function () {
                Route::get('classes/{classeId}/classement-primaire', [ResultatPrimaireController::class, 'classement'])->name('resultats-primaire.classement');
                Route::get('classes/{classeId}/remplissage-primaire', [ResultatPrimaireController::class, 'remplissage'])->name('resultats-primaire.remplissage');
                Route::get('classes/{classeId}/decisions', [ResultatPrimaireController::class, 'decisions'])->name('resultats-primaire.decisions');
            });

            Route::middleware('permission:bulletins.view')->group(function () {
                Route::get('classes/{classeId}/bulletins-primaire', [BulletinPrimaireController::class, 'classe'])->name('bulletins-primaire.classe');
                Route::get('eleves/{eleveId}/bulletin-primaire', [BulletinPrimaireController::class, 'show'])->name('bulletins-primaire.show');
            });

            Route::middleware('permission:notes.view')->group(function () {
                Route::get('classe-matieres/mes-affectations', [ClasseMatiereController::class, 'mesAffectations'])->name('classe-matieres.mes-affectations');
                Route::get('competences/mes-affectations', [CompetenceController::class, 'mesAffectations'])->name('competences.mes-affectations');
                Route::get('classes/{classeId}/remplissage', [ResultatController::class, 'remplissage'])->name('resultats.remplissage');
                Route::get('classes/{classeId}/classement', [ResultatController::class, 'classement'])->name('resultats.classement');
                Route::get('classes/{classeId}/classement/export', [ResultatController::class, 'exportClassement'])->name('resultats.classement.export');
                Route::get('eleves/{eleveId}/notes', [NoteEleveController::class, 'index'])->name('eleves.notes');
            });

            Route::middleware('permission:bulletins.view')->group(function () {
                Route::get('palmares', [ResultatController::class, 'palmares'])->name('resultats.palmares');
                Route::get('palmares/export', [ResultatController::class, 'exportPalmares'])->name('resultats.palmares.export');
                Route::get('palmares/pdf', [ResultatController::class, 'palmaresPdf'])->name('resultats.palmares.pdf');
                Route::get('eleves/{eleveId}/bulletin', [BulletinController::class, 'show'])->name('eleves.bulletin');
                Route::get('classes/{classeId}/bulletins', [BulletinController::class, 'classe'])->name('classes.bulletins');

                // Statistiques globales de l'établissement (équivalent des pages
                // generate_*_stats_batch_advanced.php de _smapp).
                Route::get('statistiques/pedagogiques', [StatistiqueController::class, 'pedagogiques'])->name('statistiques.pedagogiques');
                Route::get('statistiques/pedagogiques/pdf', [StatistiqueController::class, 'pedagogiquesPdf'])->name('statistiques.pedagogiques.pdf');
                Route::get('statistiques/disciplinaires', [StatistiqueController::class, 'disciplinaires'])->name('statistiques.disciplinaires');
                Route::get('statistiques/disciplinaires/pdf', [StatistiqueController::class, 'disciplinairesPdf'])->name('statistiques.disciplinaires.pdf');
            });

            Route::middleware('permission:bulletins.publish')->group(function () {
                Route::post('classes/{classeId}/bulletins/publier', [BulletinController::class, 'publier'])->name('classes.bulletins.publier');
            });

            Route::middleware('permission:revendications.view')->group(function () {
                Route::get('revendications', [RevendicationController::class, 'index'])->name('revendications.index');
            });

            Route::post('revendications', [RevendicationController::class, 'store'])->name('revendications.store')->middleware('permission:revendications.create');
            Route::put('revendications/{id}', [RevendicationController::class, 'update'])->name('revendications.update')->middleware('permission:revendications.update');
            Route::delete('revendications/{id}', [RevendicationController::class, 'destroy'])->name('revendications.destroy')->middleware('permission:revendications.delete');

            Route::middleware('permission:emploi_du_temps.view')->group(function () {
                Route::get('emploi-du-temps/classes', [EmploiDuTempsController::class, 'classes'])->name('edt.classes');
                Route::get('salles', [SalleController::class, 'index'])->name('salles.index');
                Route::get('emploi-du-temps/elements', [EmploiDuTempsElementController::class, 'index'])->name('edt.elements.index');
                Route::get('classes/{classeId}/emploi-du-temps', [EmploiDuTempsController::class, 'index'])->name('edt.index');
                Route::get('classes/{classeId}/emploi-du-temps/export', [EmploiDuTempsController::class, 'export'])->name('edt.export');
                Route::get('classes/{classeId}/emploi-du-temps/export-pdf', [EmploiDuTempsController::class, 'exportPdf'])->name('edt.export-pdf');
                Route::get('classes/{classeId}/seances', [SeanceController::class, 'index'])->name('seances.index');
                Route::get('seances/{id}/appel', [SeanceController::class, 'appel'])->name('seances.appel');
            });

            Route::middleware('permission:pedagogie.view')->group(function () {
                Route::get('tronc-commun-groupes', [TroncCommunGroupeController::class, 'index'])->name('tronc-commun.index');
            });

            Route::post('tronc-commun-groupes', [TroncCommunGroupeController::class, 'store'])->name('tronc-commun.store')->middleware('permission:tronc_commun.create');
            Route::delete('tronc-commun-groupes/{id}', [TroncCommunGroupeController::class, 'destroy'])->name('tronc-commun.destroy')->middleware('permission:tronc_commun.delete');

            Route::post('salles', [SalleController::class, 'store'])->name('salles.store')->middleware('permission:salles.create');
            Route::put('salles/{id}', [SalleController::class, 'update'])->name('salles.update')->middleware('permission:salles.update');
            Route::delete('salles/{id}', [SalleController::class, 'destroy'])->name('salles.destroy')->middleware('permission:salles.delete');
            Route::post('emploi-du-temps/elements', [EmploiDuTempsElementController::class, 'store'])->name('edt.elements.store')->middleware('permission:edt_elements.create');
            Route::put('emploi-du-temps/elements/{id}', [EmploiDuTempsElementController::class, 'update'])->name('edt.elements.update')->middleware('permission:edt_elements.update');
            Route::delete('emploi-du-temps/elements/{id}', [EmploiDuTempsElementController::class, 'destroy'])->name('edt.elements.destroy')->middleware('permission:edt_elements.delete');
            Route::post('emploi-du-temps/elements/{id}/appliquer', [EmploiDuTempsElementController::class, 'apply'])->name('edt.elements.apply')->middleware('permission:edt_elements.appliquer');
            Route::post('classes/{classeId}/emploi-du-temps', [EmploiDuTempsController::class, 'store'])->name('edt.store')->middleware('permission:emploi_du_temps.create');
            Route::delete('emploi-du-temps', [EmploiDuTempsController::class, 'supprimerTout'])->name('edt.supprimer-tout')->middleware('permission:emploi_du_temps.delete');
            Route::put('classes/{classeId}/emploi-du-temps/{id}', [EmploiDuTempsController::class, 'update'])->name('edt.update')->middleware('permission:emploi_du_temps.update');
            Route::delete('classes/{classeId}/emploi-du-temps/{id}', [EmploiDuTempsController::class, 'destroy'])->name('edt.destroy')->middleware('permission:emploi_du_temps.delete');
            Route::post('classes/{classeId}/emploi-du-temps/batch-delete', [EmploiDuTempsController::class, 'batchDelete'])->name('edt.batch-delete')->middleware('permission:emploi_du_temps.delete');
            Route::post('classes/{classeId}/emploi-du-temps/generer-seances', [EmploiDuTempsController::class, 'genererSeances'])->name('edt.generer')->middleware('permission:seances.generer');
            Route::post('classes/{classeId}/emploi-du-temps/supprimer-seances', [EmploiDuTempsController::class, 'supprimerSeances'])->name('edt.supprimer-seances')->middleware('permission:seances.generer');
            Route::post('classes/{classeId}/emploi-du-temps/copier', [EmploiDuTempsController::class, 'copier'])->name('edt.copier')->middleware('permission:emploi_du_temps.create');
            Route::post('classes/{classeId}/emploi-du-temps/import', [EmploiDuTempsController::class, 'import'])->name('edt.import')->middleware('permission:emploi_du_temps.import');
            Route::post('classes/{classeId}/seances', [SeanceController::class, 'store'])->name('seances.store')->middleware('permission:seances.create');
            Route::put('seances/{id}', [SeanceController::class, 'update'])->name('seances.update')->middleware('permission:seances.update');
            Route::delete('seances/{id}', [SeanceController::class, 'destroy'])->name('seances.destroy')->middleware('permission:seances.delete');
            Route::post('classes/{classeId}/seances/batch-delete', [SeanceController::class, 'batchDelete'])->name('seances.batch-delete')->middleware('permission:seances.delete');

            Route::middleware('permission:appel.saisir')->group(function () {
                Route::post('seances/{id}/appel', [SeanceController::class, 'enregistrerAppel'])->name('seances.appel.store');
            });

            Route::middleware('permission:discipline.view')->group(function () {
                Route::get('classes/{classeId}/absences', [AbsenceController::class, 'index'])->name('absences.index');
                Route::get('classes/{classeId}/bilan-disciplinaire', [AbsenceController::class, 'bilan'])->name('absences.bilan');
                Route::get('classes/{classeId}/frequentation', [AbsenceController::class, 'frequentation'])->name('absences.frequentation');
                Route::get('classes/{classeId}/bilan-disciplinaire/pdf', [AbsenceController::class, 'bilanPdf'])->name('absences.bilan.pdf');
                Route::get('classes/{classeId}/fiche-appel/pdf', [AbsenceController::class, 'ficheHebdomadairePdf'])->name('absences.fiche-appel.pdf');
                Route::get('sanctions', [SanctionController::class, 'index'])->name('sanctions.index');
                Route::get('eleves/{eleveId}/sanctions', [SanctionController::class, 'dossier'])->name('sanctions.dossier');
                Route::get('sanctions/pv-conseil/pdf', [SanctionController::class, 'pvConseilPdf'])->name('sanctions.pv-conseil-pdf');
            });

            /*
             * Finances — scolarité. La consultation, l'encaissement et
             * l'annulation relèvent de privilèges distincts : l'économe encaisse
             * au comptoir sans pouvoir défaire un reçu déjà remis.
             */
            Route::middleware('permission:finance.view')->group(function () {
                Route::get('scolarite/situation', [ScolariteController::class, 'situation'])->name('scolarite.situation');
                Route::get('scolarite/situation/export', [ScolariteController::class, 'exportSituation'])->name('scolarite.situation.export');
                Route::get('versements/{id}/recu', [ScolariteController::class, 'recu'])->name('scolarite.recu');

                Route::get('finance/insolvables', [InsolvablesController::class, 'index'])->name('finance.insolvables');
                Route::get('finance/insolvables/pdf', [InsolvablesController::class, 'pdf'])->name('finance.insolvables.pdf');
                Route::get('finance/insolvables/excel', [InsolvablesController::class, 'excel'])->name('finance.insolvables.excel');
                Route::get('finance/remises/pdf', [RemiseController::class, 'pdf'])->name('finance.remises.pdf');

                Route::get('finance/dettes-anterieures', [DetteAnterieureController::class, 'liste'])->name('finance.dettes-anterieures');
                Route::get('finance/dettes-anterieures/pdf', [DetteAnterieureController::class, 'pdf'])->name('finance.dettes-anterieures.pdf');
                Route::get('finance/dettes-anterieures/excel', [DetteAnterieureController::class, 'excel'])->name('finance.dettes-anterieures.excel');

                Route::get('eleves/{eleveId}/moratoires', [MoratoireController::class, 'index'])->name('moratoires.index');
                Route::get('eleves/{eleveId}/remises', [RemiseController::class, 'index'])->name('remises.index');
                Route::get('eleves/{eleveId}/dettes-anterieures', [DetteAnterieureController::class, 'index'])->name('dettes-anterieures.index');
            });

            /*
             * Fiche élève : situation financière et transport en lecture
             * seule. `eleves.situation` les ouvre à l'enseignant sans lui
             * donner la caisse ni la flotte ; le contrôleur borne l'élève au
             * périmètre du compte (ses classes).
             */
            Route::middleware('permission:finance.view|eleves.situation')->group(function () {
                Route::get('eleves/{eleveId}/scolarite', [ScolariteController::class, 'dossier'])->name('scolarite.dossier');
            });
            Route::middleware('permission:bus.view|eleves.situation')->group(function () {
                Route::get('eleves/{eleveId}/transport', [BusAffectationController::class, 'eleve'])->name('bus.eleve');
            });

            /*
             * Vue enseignant : insolvables et élèves transportés de ses
             * classes, sans aucun montant (cf. SituationEnseignantController).
             */
            Route::middleware('permission:eleves.situation')->group(function () {
                Route::get('enseignant/insolvables', [SituationEnseignantController::class, 'insolvables'])->name('enseignant.insolvables');
                Route::get('enseignant/bus', [SituationEnseignantController::class, 'bus'])->name('enseignant.bus');
            });

            Route::middleware('permission:finance.encaisser')->group(function () {
                Route::post('scolarite/dossiers/{id}/versements', [ScolariteController::class, 'encaisser'])->name('scolarite.encaisser');
            });

            Route::middleware('permission:finance.annuler')->group(function () {
                Route::post('versements/{id}/annuler', [ScolariteController::class, 'annuler'])->name('scolarite.annuler');
            });

            /*
             * Moratoires, remises individuelles et dettes antérieures : des
             * corrections à la situation d'un élève, réservées à qui peut
             * décider un montant — un privilège par geste, pas le simple encaissement.
             */
            Route::post('finance/dettes-anterieures/import', [DetteAnterieureController::class, 'import'])->name('finance.dettes-anterieures.import')->middleware('permission:dettes_anterieures.import');
            Route::post('eleves/{eleveId}/moratoires', [MoratoireController::class, 'store'])->name('moratoires.store')->middleware('permission:moratoires.create');
            Route::delete('moratoires/{id}', [MoratoireController::class, 'destroy'])->name('moratoires.destroy')->middleware('permission:moratoires.delete');

            Route::post('eleves/{eleveId}/remises', [RemiseController::class, 'store'])->name('remises.store')->middleware('permission:remises.create');
            Route::put('remises/{id}', [RemiseController::class, 'update'])->name('remises.update')->middleware('permission:remises.update');
            Route::delete('remises/{id}', [RemiseController::class, 'destroy'])->name('remises.destroy')->middleware('permission:remises.delete');

            Route::post('eleves/{eleveId}/dettes-anterieures', [DetteAnterieureController::class, 'store'])->name('dettes-anterieures.store')->middleware('permission:dettes_anterieures.create');
            Route::delete('dettes-anterieures/{id}', [DetteAnterieureController::class, 'destroy'])->name('dettes-anterieures.destroy')->middleware('permission:dettes_anterieures.delete');
            Route::post('eleves/{eleveId}/dettes-anterieures/oublier', [DetteAnterieureController::class, 'oublier'])->name('dettes-anterieures.oublier')->middleware('permission:dettes_anterieures.oublier');

            /*
             * Finances — dépenses. La consultation relève de `finance.view`,
             * la saisie et l'annulation de `finance.depenses` : lire le bilan
             * ne doit pas permettre d'y ajouter une ligne.
             */
            Route::middleware('permission:finance.view')->group(function () {
                Route::get('depenses', [DepenseController::class, 'index'])->name('depenses.index');
                Route::get('comptes-comptables', [DepenseController::class, 'comptes'])->name('comptes.index');
            });

            Route::middleware('permission:finance.depenses')->group(function () {
                Route::post('depenses', [DepenseController::class, 'store'])->name('depenses.store');
                Route::post('depenses/import', [DepenseController::class, 'import'])->name('depenses.import');
                Route::post('depenses/{id}/payer', [DepenseController::class, 'payer'])->name('depenses.payer');
                Route::post('depenses/{id}/annuler', [DepenseController::class, 'annuler'])->name('depenses.annuler');
                // Le sélecteur « Source = Budget alloué » du formulaire de dépense
                // a besoin de la liste des budgets actifs, sans pour autant
                // donner le droit d'en allouer un (finance.budget).
                Route::get('budgets-personnel/actifs', [BudgetPersonnelController::class, 'actifs'])->name('budgets-personnel.actifs');
            });

            Route::middleware('permission:finance.budget')->group(function () {
                Route::get('budgets-personnel', [BudgetPersonnelController::class, 'index'])->name('budgets-personnel.index');
                Route::post('budgets-personnel', [BudgetPersonnelController::class, 'store'])->name('budgets-personnel.store');
                Route::get('budgets-personnel/{id}', [BudgetPersonnelController::class, 'show'])->name('budgets-personnel.show');
                Route::put('budgets-personnel/{id}/note-gestion', [BudgetPersonnelController::class, 'modifierNoteGestion'])->name('budgets-personnel.note-gestion');
                Route::post('budgets-personnel/{id}/annuler', [BudgetPersonnelController::class, 'annuler'])->name('budgets-personnel.annuler');
                Route::get('budgets-personnel/{id}/bilan/pdf', [BudgetPersonnelController::class, 'bilanPdf'])->name('budgets-personnel.bilan-pdf');
            });

            Route::middleware('permission:finance.rapports')->group(function () {
                Route::get('depenses/bilan/pdf', [DepenseController::class, 'bilanPdf'])->name('depenses.bilan-pdf');

                /*
                 * État de synthèse des charges et dépenses : le document que
                 * tient l'établissement, exercice par exercice, plus la lecture
                 * qui sépare exploitation, investissement et capital.
                 */
                Route::get('etat-synthese', [EtatSyntheseController::class, 'show'])->name('etat-synthese.show');
                Route::get('etat-synthese/pdf', [EtatSyntheseController::class, 'pdf'])->name('etat-synthese.pdf');
                Route::get('etat-synthese/serie', [EtatSyntheseController::class, 'serie'])->name('etat-synthese.serie');
                Route::get('etat-synthese/serie/pdf', [EtatSyntheseController::class, 'seriePdf'])->name('etat-synthese.serie-pdf');
                Route::get('etat-synthese/exercices', [EtatSyntheseController::class, 'exercices'])->name('etat-synthese.exercices');
                Route::get('prelevements-eleve', [EtatSyntheseController::class, 'prelevements'])->name('prelevements-eleve.index');
                Route::get('amortissements', [EtatSyntheseController::class, 'amortissements'])->name('amortissements.index');
            });

            /*
             * Régulariser passe des dépenses : c'est un acte de gestion, pas
             * une consultation de rapport.
             */
            Route::middleware('permission:finance.depenses')->group(function () {
                Route::post('prelevements-eleve/regulariser', [EtatSyntheseController::class, 'regulariser'])->name('prelevements-eleve.regulariser');
                Route::post('amortissements/doter', [EtatSyntheseController::class, 'doter'])->name('amortissements.doter');
                Route::patch('immobilisations/{id}', [EtatSyntheseController::class, 'reviserImmobilisation'])->name('immobilisations.update');
            });

            /*
             * Tarifs : `finance.view` pour consulter la grille, `tarifs.*`,
             * `frais_annexes.*` et `tranches_scolarite.*` pour la fixer — décider
             * d'un prix n'est pas le métier du caissier.
             */
            Route::middleware('permission:finance.view')->group(function () {
                Route::get('tarifs', [TarifsController::class, 'index'])->name('tarifs.index');
                Route::get('tarifs/grille-frais/export', [TarifsController::class, 'exportGrilleFrais'])->name('tarifs.grille-frais.export');
                Route::get('tarifs/grille-frais/modele', [TarifsController::class, 'modeleGrilleFrais'])->name('tarifs.grille-frais.modele');
                Route::get('tarifs/frais-annexes/export', [TarifsController::class, 'exportFraisAnnexes'])->name('tarifs.frais-annexes.export');
                Route::get('tarifs/frais-annexes/modele', [TarifsController::class, 'modeleFraisAnnexes'])->name('tarifs.frais-annexes.modele');
                // Échéancier de la scolarité : le découpage de l'année en
                // tranches, lu par le portail parent et par les insolvables.
                Route::get('tranches-scolarite', [TrancheScolariteController::class, 'index'])->name('tranches-scolarite.index');
                Route::get('tranches-scolarite/export', [TrancheScolariteController::class, 'export'])->name('tranches-scolarite.export');
                Route::get('tranches-scolarite/modele', [TrancheScolariteController::class, 'modele'])->name('tranches-scolarite.modele');
            });

            Route::put('tranches-scolarite', [TrancheScolariteController::class, 'remplacer'])->name('tranches-scolarite.remplacer')->middleware('permission:tranches_scolarite.update');
            Route::post('tranches-scolarite/import', [TrancheScolariteController::class, 'import'])->name('tranches-scolarite.import')->middleware('permission:tranches_scolarite.import');
            Route::post('tarifs/grille-frais/import', [TarifsController::class, 'importGrilleFrais'])->name('tarifs.grille-frais.import')->middleware('permission:tarifs.import');
            Route::post('tarifs/frais-annexes/import', [TarifsController::class, 'importFraisAnnexes'])->name('tarifs.frais-annexes.import')->middleware('permission:frais_annexes.import');
            Route::post('tarifs', [TarifsController::class, 'definirTarif'])->name('tarifs.definir')->middleware('permission:tarifs.update');
            Route::post('tarifs/synchroniser-dossiers', [TarifsController::class, 'synchroniserDossiers'])->name('tarifs.synchroniser-dossiers')->middleware('permission:tarifs.update');
            Route::delete('tarifs/classes/{classeId}', [TarifsController::class, 'supprimerTarif'])->name('tarifs.supprimer')->middleware('permission:tarifs.delete');
            Route::post('tarifs/frais-annexes', [TarifsController::class, 'creerFraisAnnexe'])->name('tarifs.frais.store')->middleware('permission:frais_annexes.create');
            Route::put('tarifs/frais-annexes/{id}', [TarifsController::class, 'modifierFraisAnnexe'])->name('tarifs.frais.update')->middleware('permission:frais_annexes.update');
            Route::delete('tarifs/frais-annexes/{id}', [TarifsController::class, 'desactiverFraisAnnexe'])->name('tarifs.frais.destroy')->middleware('permission:frais_annexes.delete');

            Route::middleware('permission:finance.rapports')->group(function () {
                Route::get('rapports/tableau-de-bord', [RapportFinancierController::class, 'tableauDeBord'])->name('rapports.bord');
                Route::get('rapports/resultat', [RapportFinancierController::class, 'resultat'])->name('rapports.resultat');
                Route::get('rapports/tresorerie', [RapportFinancierController::class, 'tresorerie'])->name('rapports.tresorerie');
                Route::get('rapports/balance', [RapportFinancierController::class, 'balance'])->name('rapports.balance');
                Route::get('budget-fonctionnement', [BudgetFonctionnementController::class, 'index'])->name('budget-fonctionnement.index');
                Route::get('assurances-scolaires', [AssuranceScolaireController::class, 'index'])->name('assurances-scolaires.index');
                Route::get('conseil-ecole', [ConseilEcoleController::class, 'index'])->name('conseil-ecole.index');
                Route::get('apee', [ApeeController::class, 'index'])->name('apee.index');
            });

            Route::put('budget-fonctionnement/{rubrique}', [BudgetFonctionnementController::class, 'update'])->name('budget-fonctionnement.update')->middleware('permission:budget_fonctionnement.update');
            Route::post('assurances-scolaires', [AssuranceScolaireController::class, 'store'])->name('assurances-scolaires.store')->middleware('permission:assurances_scolaires.create');
            Route::put('assurances-scolaires/{id}', [AssuranceScolaireController::class, 'update'])->name('assurances-scolaires.update')->middleware('permission:assurances_scolaires.update');
            Route::delete('assurances-scolaires/{id}', [AssuranceScolaireController::class, 'destroy'])->name('assurances-scolaires.destroy')->middleware('permission:assurances_scolaires.delete');
            Route::put('conseil-ecole', [ConseilEcoleController::class, 'update'])->name('conseil-ecole.update')->middleware('permission:conseil_ecole.update');
            Route::put('apee', [ApeeController::class, 'update'])->name('apee.update')->middleware('permission:apee.update');

            /*
             * Finances — paie. Un seul privilège : préparer, arrêter et régler
             * la paie forment une même responsabilité, et personne ne prépare
             * un bulletin sans pouvoir le mener au bout.
             */
            Route::middleware('permission:finance.paie')->group(function () {
                Route::get('remunerations', [RemunerationController::class, 'index'])->name('remunerations.index');
                Route::get('remunerations/modele', [RemunerationController::class, 'modele'])->name('remunerations.modele');
                Route::get('remunerations/{personnelId}/historique', [RemunerationController::class, 'historique'])->name('remunerations.historique');
                Route::get('remunerations/{personnelId}/anciennete', [RemunerationController::class, 'anciennete'])->name('remunerations.anciennete');
                Route::delete('remunerations/{id}', [RemunerationController::class, 'supprimer'])->name('remunerations.supprimer');
                Route::post('remunerations/appliquer', [RemunerationController::class, 'appliquer'])->name('remunerations.appliquer');
                Route::post('remunerations/simuler', [RemunerationController::class, 'simuler'])->name('remunerations.simuler');
                // Déclarée avant « remunerations/{personnelId} » : sinon « import »
                // s'y ferait happer comme un identifiant d'agent.
                Route::post('remunerations/import', [RemunerationController::class, 'import'])->name('remunerations.import');
                Route::post('remunerations/{personnelId}', [RemunerationController::class, 'store'])->name('remunerations.store');

                Route::get('paie', [PaieController::class, 'index'])->name('paie.index');
                Route::get('paie/etat-emargement', [PaieController::class, 'etatEmargement'])->name('paie.emargement');
                Route::get('paie/bordereau', [PaieController::class, 'bordereau'])->name('paie.bordereau');
                Route::get('paie/bordereau/pdf', [PaieController::class, 'bordereauPdf'])->name('paie.bordereau-pdf');
                Route::post('paie/preparer', [PaieController::class, 'preparerLot'])->name('paie.preparer-lot');
                Route::post('paie/personnels/{personnelId}/preparer', [PaieController::class, 'preparer'])->name('paie.preparer');
                Route::get('paie/bulletins/{id}/pdf', [PaieController::class, 'bulletinPdf'])->name('paie.bulletin-pdf');
                Route::post('paie/bulletins/arreter-lot', [PaieController::class, 'arreterLot'])->name('paie.arreter-lot');
                Route::post('paie/bulletins/{id}/arreter', [PaieController::class, 'arreter'])->name('paie.arreter');
                Route::post('paie/bulletins/payer-lot', [PaieController::class, 'payerLot'])->name('paie.payer-lot');
                Route::post('paie/bulletins/{id}/payer', [PaieController::class, 'payer'])->name('paie.payer');
                Route::post('paie/bulletins/{id}/emarger', [PaieController::class, 'emarger'])->name('paie.emarger');

                Route::get('avances-salaire', [AvanceSalaireController::class, 'index'])->name('avances-salaire.index');
                Route::get('avances-salaire/plafond', [AvanceSalaireController::class, 'plafond'])->name('avances-salaire.plafond');
                Route::post('avances-salaire', [AvanceSalaireController::class, 'store'])->name('avances-salaire.store');
                Route::post('avances-salaire/{id}/remboursements', [AvanceSalaireController::class, 'rembourser'])->name('avances-salaire.rembourser');
                Route::post('avances-salaire/{id}/annuler', [AvanceSalaireController::class, 'annuler'])->name('avances-salaire.annuler');

                Route::get('demandes-avance-salaire', [DemandeAvanceSalaireAdminController::class, 'index'])->name('demandes-avance-salaire.index');
                Route::post('demandes-avance-salaire/{id}/valider', [DemandeAvanceSalaireAdminController::class, 'valider'])->name('demandes-avance-salaire.valider');
                Route::post('demandes-avance-salaire/{id}/rejeter', [DemandeAvanceSalaireAdminController::class, 'rejeter'])->name('demandes-avance-salaire.rejeter');
            });

            Route::post('classes/{classeId}/absences', [AbsenceController::class, 'bulkStore'])->name('absences.bulk-store')->middleware('permission:absences.saisir');
            Route::post('sanctions', [SanctionController::class, 'store'])->name('sanctions.store')->middleware('permission:sanctions.create');
            Route::put('sanctions/{id}', [SanctionController::class, 'update'])->name('sanctions.update')->middleware('permission:sanctions.update');
            Route::delete('sanctions/{id}', [SanctionController::class, 'destroy'])->name('sanctions.destroy')->middleware('permission:sanctions.delete');

            Route::middleware('permission:infirmerie.view')->group(function () {
                Route::get('infirmerie/visites', [VisiteInfirmerieController::class, 'index'])->name('infirmerie.visites.index');
                Route::get('infirmerie/visites/export', [VisiteInfirmerieController::class, 'export'])->name('infirmerie.visites.export');
                Route::get('infirmerie/visites/modele', [VisiteInfirmerieController::class, 'modele'])->name('infirmerie.visites.modele');
                Route::get('infirmerie/malaises', [MalaiseReferentielController::class, 'index'])->name('infirmerie.malaises.index');
            });

            Route::post('infirmerie/visites/import', [VisiteInfirmerieController::class, 'import'])->name('infirmerie.visites.import')->middleware('permission:infirmerie.import');
            Route::post('infirmerie/visites', [VisiteInfirmerieController::class, 'store'])->name('infirmerie.visites.store')->middleware('permission:infirmerie.create');
            Route::put('infirmerie/visites/{id}', [VisiteInfirmerieController::class, 'update'])->name('infirmerie.visites.update')->middleware('permission:infirmerie.update');
            Route::delete('infirmerie/visites/{id}', [VisiteInfirmerieController::class, 'destroy'])->name('infirmerie.visites.destroy')->middleware('permission:infirmerie.delete');
            Route::post('infirmerie/malaises', [MalaiseReferentielController::class, 'store'])->name('infirmerie.malaises.store')->middleware('permission:malaises.create');
            Route::put('infirmerie/malaises/{id}', [MalaiseReferentielController::class, 'update'])->name('infirmerie.malaises.update')->middleware('permission:malaises.update');
            Route::delete('infirmerie/malaises/{id}', [MalaiseReferentielController::class, 'destroy'])->name('infirmerie.malaises.destroy')->middleware('permission:malaises.delete');

            Route::middleware('permission:bus.view')->group(function () {
                Route::get('bus/vehicules', [BusVehiculeController::class, 'index'])->name('bus.vehicules.index');
                Route::get('bus/vehicules/export', [BusVehiculeController::class, 'export'])->name('bus.vehicules.export');
                Route::get('bus/vehicules/modele', [BusVehiculeController::class, 'modele'])->name('bus.vehicules.modele');
                Route::get('bus/vehicules/{id}/eleves/pdf', [BusVehiculeController::class, 'elevesPdf'])->name('bus.vehicules.eleves-pdf');
                Route::get('bus/vehicules/{id}/bilan/pdf', [BusVehiculeController::class, 'bilanPdf'])->name('bus.vehicules.bilan-pdf');
                Route::get('bus/trajets', [BusTrajetController::class, 'index'])->name('bus.trajets.index');
                Route::get('bus/trajets/{id}', [BusTrajetController::class, 'show'])->name('bus.trajets.show');
                Route::get('bus/affectations', [BusAffectationController::class, 'index'])->name('bus.affectations.index');
                Route::get('bus/affectations/liste-personnalisee/modeles', [BusAffectationController::class, 'modelesListePersonnalisee'])->name('bus.affectations.liste-personnalisee.modeles.index');
                Route::post('bus/affectations/liste-personnalisee/modeles', [BusAffectationController::class, 'storeModeleListePersonnalisee'])->name('bus.affectations.liste-personnalisee.modeles.store');
                Route::put('bus/affectations/liste-personnalisee/modeles/{id}', [BusAffectationController::class, 'updateModeleListePersonnalisee'])->name('bus.affectations.liste-personnalisee.modeles.update');
                Route::delete('bus/affectations/liste-personnalisee/modeles/{id}', [BusAffectationController::class, 'destroyModeleListePersonnalisee'])->name('bus.affectations.liste-personnalisee.modeles.destroy');
                Route::get('bus/affectations/liste-personnalisee', [BusAffectationController::class, 'listePersonnalisee'])->name('bus.affectations.liste-personnalisee');
                Route::get('bus/affectations/liste-personnalisee/pdf', [BusAffectationController::class, 'listePersonnaliseePdf'])->name('bus.affectations.liste-personnalisee-pdf');
                Route::get('bus/affectations/liste-personnalisee/word', [BusAffectationController::class, 'listePersonnaliseeWord'])->name('bus.affectations.liste-personnalisee-word');
                Route::get('bus/affectations/liste-personnalisee/excel', [BusAffectationController::class, 'listePersonnaliseeExcel'])->name('bus.affectations.liste-personnalisee-excel');
                Route::get('bus/affectations/export', [BusAffectationController::class, 'export'])->name('bus.affectations.export');
                Route::get('bus/affectations/modele', [BusAffectationController::class, 'modele'])->name('bus.affectations.modele');
                Route::get('bus/eleves', [BusAffectationController::class, 'eleves'])->name('bus.eleves');
                Route::get('bus/stats', [BusAffectationController::class, 'stats'])->name('bus.stats');
                Route::get('bus/affectations/{id}/versements', [BusPaiementController::class, 'situation'])->name('bus.paiements.situation');
                Route::get('bus/versements/{id}/recu', [BusPaiementController::class, 'recu'])->name('bus.paiements.recu');
            });

            Route::post('bus/vehicules/import', [BusVehiculeController::class, 'import'])->name('bus.vehicules.import')->middleware('permission:bus_vehicules.import');
            Route::post('bus/vehicules', [BusVehiculeController::class, 'store'])->name('bus.vehicules.store')->middleware('permission:bus_vehicules.create');
            Route::put('bus/vehicules/{id}', [BusVehiculeController::class, 'update'])->name('bus.vehicules.update')->middleware('permission:bus_vehicules.update');
            Route::delete('bus/vehicules/{id}', [BusVehiculeController::class, 'destroy'])->name('bus.vehicules.destroy')->middleware('permission:bus_vehicules.delete');

            Route::post('bus/trajets', [BusTrajetController::class, 'store'])->name('bus.trajets.store')->middleware('permission:bus_trajets.create');
            Route::post('bus/trajets/import', [BusTrajetController::class, 'importTrajets'])->name('bus.trajets.import')->middleware('permission:bus_trajets.import');
            Route::post('bus/arrets/import', [BusTrajetController::class, 'importArrets'])->name('bus.arrets.import')->middleware('permission:bus_arrets.import');
            Route::put('bus/trajets/{id}', [BusTrajetController::class, 'update'])->name('bus.trajets.update')->middleware('permission:bus_trajets.update');
            Route::delete('bus/trajets/{id}', [BusTrajetController::class, 'destroy'])->name('bus.trajets.destroy')->middleware('permission:bus_trajets.delete');
            Route::post('bus/trajets/{id}/notifier', [BusTrajetController::class, 'notifier'])->name('bus.trajets.notifier')->middleware('permission:bus_trajets.notifier');
            Route::post('bus/trajets/{trajetId}/arrets', [BusTrajetController::class, 'ajouterArret'])->name('bus.arrets.store')->middleware('permission:bus_arrets.create');
            Route::put('bus/trajets/{trajetId}/arrets/{arretId}', [BusTrajetController::class, 'modifierArret'])->name('bus.arrets.update')->middleware('permission:bus_arrets.update');
            Route::delete('bus/trajets/{trajetId}/arrets/{arretId}', [BusTrajetController::class, 'supprimerArret'])->name('bus.arrets.destroy')->middleware('permission:bus_arrets.delete');

            // Souscrire/retirer un élève et encaisser ses paiements : distinct de
            // la flotte, des trajets et des arrêts, pour qu'un profil autorisé à
            // consulter et saisir des dépenses ne puisse pas, pour autant,
            // inscrire ou désinscrire des élèves du transport.
            Route::middleware('permission:bus.souscrire')->group(function () {
                Route::post('bus/affectations', [BusAffectationController::class, 'store'])->name('bus.affectations.store');
                Route::post('bus/affectations/import', [BusAffectationController::class, 'import'])->name('bus.affectations.import');
                Route::post('bus/souscriptions-lot', [BusAffectationController::class, 'souscrireLot'])->name('bus.affectations.souscrire-lot');
                Route::put('bus/affectations/{id}', [BusAffectationController::class, 'update'])->name('bus.affectations.update');
                // Avant `bus/affectations/{id}` juste en dessous : même précaution
                // que pour `eleves/non-preinscrits-sans-historique` plus haut.
                Route::delete('bus/affectations/batch-destroy', [BusAffectationController::class, 'batchDestroy'])->name('bus.affectations.batch-destroy');
                Route::delete('bus/affectations/{id}', [BusAffectationController::class, 'destroy'])->name('bus.affectations.destroy');

                Route::post('bus/affectations/{id}/versements', [BusPaiementController::class, 'encaisser'])->name('bus.paiements.encaisser');
                Route::post('bus/versements/{id}/annuler', [BusPaiementController::class, 'annuler'])->name('bus.paiements.annuler');
            });

            /*
             * Espace chauffeur : sa tournée vue du siège du conducteur —
             * effectif transporté, rentabilité de son bus, itinéraire arrêt
             * par arrêt avec le pointage des enfants déjà pris, et le contact
             * de leurs familles. Périmètre « moi-même » borné dans le service
             * aux véhicules que le compte conduit ce jour-là : `bus.view`
             * ouvrirait sinon la flotte entière (cf. ChauffeurService).
             *
             * Aucune route de souscription ni de modification de trajet ou
             * d'arrêt n'y figure : ce n'est pas le métier du chauffeur, et
             * celles qui existent restent derrière `bus.souscrire` et
             * `bus_trajets.*`/`bus_arrets.*` ci-dessus.
             */
            Route::prefix('chauffeur')->name('chauffeur.')->middleware('permission:bus.view')->group(function () {
                Route::get('tableau-de-bord', [ChauffeurEspaceController::class, 'tableauDeBord'])->name('tableau-de-bord');
                Route::get('itineraire', [ChauffeurEspaceController::class, 'itineraire'])->name('itineraire');
                Route::get('eleves', [ChauffeurEspaceController::class, 'eleves'])->name('eleves');
                Route::get('eleves/{id}', [ChauffeurEspaceController::class, 'profilEleve'])->name('eleves.show');
                Route::get('depenses', [ChauffeurEspaceController::class, 'depenses'])->name('depenses');
                Route::get('itineraires-disponibles', [ChauffeurEspaceController::class, 'itinerairesDisponibles'])->name('itineraires-disponibles');

                Route::post('ramassages', [ChauffeurEspaceController::class, 'pointer'])->name('ramassages.store')->middleware('permission:bus.ramassage');

                Route::middleware('permission:bus.remplacement')->group(function () {
                    Route::post('empechements', [ChauffeurEspaceController::class, 'declarerEmpechement'])->name('empechements.store');
                    Route::post('empechements/{id}/reprendre', [ChauffeurEspaceController::class, 'reprendre'])->name('empechements.reprendre');
                    Route::post('empechements/{id}/annuler', [ChauffeurEspaceController::class, 'annulerEmpechement'])->name('empechements.annuler');
                });
            });

            /*
             * Pendant administratif : la direction ouvre un relais sur
             * n'importe quel bus de la flotte et peut désigner le remplaçant
             * elle-même — un chauffeur injoignable ne doit pas laisser un
             * circuit sans bus.
             */
            Route::middleware('permission:bus.remplacement')->group(function () {
                Route::get('bus/remplacements', [BusRemplacementController::class, 'index'])->name('bus.remplacements.index');
                Route::post('bus/remplacements', [BusRemplacementController::class, 'store'])->name('bus.remplacements.store');
                Route::post('bus/remplacements/{id}/attribuer', [BusRemplacementController::class, 'attribuer'])->name('bus.remplacements.attribuer');
                Route::post('bus/remplacements/{id}/annuler', [BusRemplacementController::class, 'annuler'])->name('bus.remplacements.annuler');
            });

            Route::middleware('permission:inventaire.view')->group(function () {
                Route::get('inventaire', [InventaireController::class, 'index'])->name('inventaire.index');
                Route::get('inventaire/export', [InventaireController::class, 'export'])->name('inventaire.export');
                Route::get('inventaire/modele', [InventaireController::class, 'modele'])->name('inventaire.modele');
            });
            Route::post('inventaire/import', [InventaireController::class, 'import'])->name('inventaire.import')->middleware('permission:inventaire.import');
            Route::post('inventaire', [InventaireController::class, 'store'])->name('inventaire.store')->middleware('permission:inventaire.create');
            // Déclarées avant « inventaire/{id} » : sans cela, « etiquettes »
            // serait capté comme un identifiant d'article.
            Route::post('inventaire/etiquettes', [InventaireController::class, 'etiquettes'])->name('inventaire.etiquettes')->middleware('permission:inventaire.etiquettes');
            Route::post('inventaire/{id}/code-barre', [InventaireController::class, 'codeBarre'])->name('inventaire.code-barre')->middleware('permission:inventaire.etiquettes');
            Route::put('inventaire/{id}', [InventaireController::class, 'update'])->name('inventaire.update')->middleware('permission:inventaire.update');
            Route::delete('inventaire/{id}', [InventaireController::class, 'destroy'])->name('inventaire.destroy')->middleware('permission:inventaire.delete');

            Route::get('demandes-articles-inventaire', [DemandeArticleInventaireAdminController::class, 'index'])->name('demandes-articles-inventaire.index')->middleware('permission:demandes_articles.view');
            Route::post('demandes-articles-inventaire/{id}/valider', [DemandeArticleInventaireAdminController::class, 'valider'])->name('demandes-articles-inventaire.valider')->middleware('permission:demandes_articles.valider');
            Route::post('demandes-articles-inventaire/{id}/rejeter', [DemandeArticleInventaireAdminController::class, 'rejeter'])->name('demandes-articles-inventaire.rejeter')->middleware('permission:demandes_articles.valider');

            Route::middleware('permission:infrastructures.view')->group(function () {
                Route::get('infrastructures', [InfrastructureController::class, 'index'])->name('infrastructures.index');
                Route::get('infrastructures/export', [InfrastructureController::class, 'exportInfrastructures'])->name('infrastructures.export');
                Route::get('infrastructures/modele', [InfrastructureController::class, 'modeleInfrastructures'])->name('infrastructures.modele');
                Route::get('infrastructures/equipements/export', [InfrastructureController::class, 'exportEquipements'])->name('infrastructures.equipements.export');
                Route::get('infrastructures/equipements/modele', [InfrastructureController::class, 'modeleEquipements'])->name('infrastructures.equipements.modele');
                Route::get('infrastructures/rapport', [InfrastructureController::class, 'rapport'])->name('infrastructures.rapport');
                Route::get('infrastructures/equipements', [InfrastructureController::class, 'equipements'])->name('infrastructures.equipements.index');
            });
            Route::post('infrastructures/import', [InfrastructureController::class, 'importInfrastructures'])->name('infrastructures.import')->middleware('permission:infrastructures.import');
            Route::post('infrastructures/equipements/import', [InfrastructureController::class, 'importEquipements'])->name('infrastructures.equipements.import')->middleware('permission:equipements.import');
            Route::post('infrastructures', [InfrastructureController::class, 'store'])->name('infrastructures.store')->middleware('permission:infrastructures.create');
            Route::put('infrastructures/{id}', [InfrastructureController::class, 'update'])->name('infrastructures.update')->middleware('permission:infrastructures.update');
            Route::delete('infrastructures/{id}', [InfrastructureController::class, 'destroy'])->name('infrastructures.destroy')->middleware('permission:infrastructures.delete');
            Route::post('infrastructures/equipements', [InfrastructureController::class, 'storeEquipement'])->name('infrastructures.equipements.store')->middleware('permission:equipements.create');
            Route::put('infrastructures/equipements/{id}', [InfrastructureController::class, 'updateEquipement'])->name('infrastructures.equipements.update')->middleware('permission:equipements.update');
            Route::delete('infrastructures/equipements/{id}', [InfrastructureController::class, 'destroyEquipement'])->name('infrastructures.equipements.destroy')->middleware('permission:equipements.delete');

            Route::middleware('permission:rapport_rentree.view')->group(function () {
                Route::get('visites-autorites', [VisiteAutoriteController::class, 'index'])->name('visites-autorites.index');
                Route::get('activites-rentree', [ActiviteRentreeController::class, 'index'])->name('activites-rentree.index');
                Route::get('ventes-denrees', [VenteDenreeController::class, 'index'])->name('ventes-denrees.index');
                Route::get('rapport-rentree-textes', [RapportRentreeTexteController::class, 'index'])->name('rapport-rentree-textes.index');
                Route::get('rapport-rentree/complet', [RapportRentreeExportController::class, 'donnees'])->name('rapport-rentree.complet');
                Route::get('rapport-rentree/complet/pdf', [RapportRentreeExportController::class, 'pdf'])->name('rapport-rentree.complet.pdf');
                Route::get('rapport-rentree/complet/docx', [RapportRentreeExportController::class, 'docx'])->name('rapport-rentree.complet.docx');
            });
            Route::post('visites-autorites', [VisiteAutoriteController::class, 'store'])->name('visites-autorites.store')->middleware('permission:visites_autorites.create');
            Route::put('visites-autorites/{id}', [VisiteAutoriteController::class, 'update'])->name('visites-autorites.update')->middleware('permission:visites_autorites.update');
            Route::delete('visites-autorites/{id}', [VisiteAutoriteController::class, 'destroy'])->name('visites-autorites.destroy')->middleware('permission:visites_autorites.delete');
            Route::post('activites-rentree', [ActiviteRentreeController::class, 'store'])->name('activites-rentree.store')->middleware('permission:activites_rentree.create');
            Route::put('activites-rentree/{id}', [ActiviteRentreeController::class, 'update'])->name('activites-rentree.update')->middleware('permission:activites_rentree.update');
            Route::delete('activites-rentree/{id}', [ActiviteRentreeController::class, 'destroy'])->name('activites-rentree.destroy')->middleware('permission:activites_rentree.delete');
            Route::post('ventes-denrees', [VenteDenreeController::class, 'store'])->name('ventes-denrees.store')->middleware('permission:ventes_denrees.create');
            Route::put('ventes-denrees/{id}', [VenteDenreeController::class, 'update'])->name('ventes-denrees.update')->middleware('permission:ventes_denrees.update');
            Route::delete('ventes-denrees/{id}', [VenteDenreeController::class, 'destroy'])->name('ventes-denrees.destroy')->middleware('permission:ventes_denrees.delete');
            Route::put('rapport-rentree-textes/{rubrique}', [RapportRentreeTexteController::class, 'update'])->name('rapport-rentree-textes.update')->middleware('permission:rapport_rentree.update');

            Route::middleware('permission:rapport_trimestre.view')->group(function () {
                Route::get('rapport-trimestre-textes', [RapportTrimestreTexteController::class, 'index'])->name('rapport-trimestre-textes.index');
                Route::get('rapport-trimestre/complet', [RapportTrimestreExportController::class, 'donnees'])->name('rapport-trimestre.complet');
                Route::get('rapport-trimestre/complet/docx', [RapportTrimestreExportController::class, 'docx'])->name('rapport-trimestre.complet.docx');
            });
            Route::middleware('permission:rapport_trimestre.update')->group(function () {
                Route::put('rapport-trimestre-textes/{rubrique}', [RapportTrimestreTexteController::class, 'update'])->name('rapport-trimestre-textes.update');
            });

            /*
             * Point de vente des fournitures. Le catalogue et le stock sont
             * ceux de l'inventaire : ce groupe n'ouvre qu'une caisse par-dessus.
             */
            Route::middleware('permission:point_de_vente.view')->group(function () {
                Route::get('point-de-vente/catalogue', [PointDeVenteController::class, 'catalogue'])->name('point-de-vente.catalogue');
                Route::get('point-de-vente/articles/{code}', [PointDeVenteController::class, 'parCodeBarre'])->name('point-de-vente.article-code-barre');
                Route::get('point-de-vente/ventes', [PointDeVenteController::class, 'ventes'])->name('point-de-vente.ventes');
                Route::get('point-de-vente/stats-vendeur', [PointDeVenteController::class, 'statsVendeur'])->name('point-de-vente.stats-vendeur');
                Route::get('point-de-vente/ventes/{id}/facture', [PointDeVenteController::class, 'facture'])->name('point-de-vente.facture');
                Route::get('point-de-vente/entrees', [PointDeVenteController::class, 'entrees'])->name('point-de-vente.entrees');
            });
            Route::middleware('permission:point_de_vente.vendre')->group(function () {
                Route::post('point-de-vente/ventes', [PointDeVenteController::class, 'vendre'])->name('point-de-vente.vendre');
            });
            Route::post('point-de-vente/ventes/{id}/annuler', [PointDeVenteController::class, 'annulerVente'])->name('point-de-vente.annuler')->middleware('permission:point_de_vente.annuler');
            Route::post('point-de-vente/entrees', [PointDeVenteController::class, 'entrer'])->name('point-de-vente.entrer')->middleware('permission:point_de_vente.approvisionner');
        });
    });
});
