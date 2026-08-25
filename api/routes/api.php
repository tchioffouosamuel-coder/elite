<?php

use App\Http\Controllers\Api\V1\AbsenceController;
use App\Http\Controllers\Api\V1\AnneeScolaireController;
use App\Http\Controllers\Api\V1\AnnonceController;
use App\Http\Controllers\Api\V1\AppreciationController;
use App\Http\Controllers\Api\V1\AttestationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvanceSalaireController;
use App\Http\Controllers\Api\V1\BulletinController;
use App\Http\Controllers\Api\V1\BulletinPrimaireController;
use App\Http\Controllers\Api\V1\BusAffectationController;
use App\Http\Controllers\Api\V1\BusTrajetController;
use App\Http\Controllers\Api\V1\BusVehiculeController;
use App\Http\Controllers\Api\V1\CarteScolaireController;
use App\Http\Controllers\Api\V1\ClasseController;
use App\Http\Controllers\Api\V1\ClasseMatiereController;
use App\Http\Controllers\Api\V1\CompetenceController;
use App\Http\Controllers\Api\V1\CompteController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DepartementController;
use App\Http\Controllers\Api\V1\DepenseController;
use App\Http\Controllers\Api\V1\EtatSyntheseController;
use App\Http\Controllers\Api\V1\DemandeAvanceSalaireAdminController;
use App\Http\Controllers\Api\V1\DetteAnterieureController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\EleveController;
use App\Http\Controllers\Api\V1\EleveRapportsController;
use App\Http\Controllers\Api\V1\EmploiDuTempsController;
use App\Http\Controllers\Api\V1\EnseignantController;
use App\Http\Controllers\Api\V1\EvaluationController;
use App\Http\Controllers\Api\V1\FonctionReferentielController;
use App\Http\Controllers\Api\V1\InsolvablesController;
use App\Http\Controllers\Api\V1\InventaireController;
use App\Http\Controllers\Api\V1\ListeElevesController;
use App\Http\Controllers\Api\V1\MalaiseReferentielController;
use App\Http\Controllers\Api\V1\MoratoireController;
use App\Http\Controllers\Api\V1\MaJourneeController;
use App\Http\Controllers\Api\V1\MatiereController;
use App\Http\Controllers\Api\V1\NiveauController;
use App\Http\Controllers\Api\V1\NiveauScolaireController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\NotePrimaireController;
use App\Http\Controllers\Api\V1\NotificationInterneController;
use App\Http\Controllers\Api\V1\PaieController;
use App\Http\Controllers\Api\V1\ModificationEleveAdminController;
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
use App\Http\Controllers\Api\V1\RapportFinancierController;
use App\Http\Controllers\Api\V1\RemiseController;
use App\Http\Controllers\Api\V1\RemunerationController;
use App\Http\Controllers\Api\V1\ResultatController;
use App\Http\Controllers\Api\V1\ResultatPrimaireController;
use App\Http\Controllers\Api\V1\RevendicationController;
use App\Http\Controllers\Api\V1\SanctionController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\ScolariteController;
use App\Http\Controllers\Api\V1\SeanceController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\SousSystemeController;
use App\Http\Controllers\Api\V1\StatistiqueController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TarifsController;
use App\Http\Controllers\Api\V1\TrancheScolariteController;
use App\Http\Controllers\Api\V1\TrimestreController;
use App\Http\Controllers\Api\V1\TuteurController;
use App\Http\Controllers\Api\V1\VerificationBulletinController;
use App\Http\Controllers\Api\V1\VerificationVersementController;
use App\Http\Controllers\Api\V1\VisiteInfirmerieController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    // Vérification publique d'authenticité d'un bulletin (QR code) : accessible
    // sans authentification, un tiers externe scanne depuis son téléphone.
    Route::get('verification-bulletin/{eleveId}/{trimestreId}/{signature}', [VerificationBulletinController::class, 'show'])
        ->name('verification-bulletin.show');

    // Vérification publique d'authenticité d'un reçu de versement (QR code) :
    // même principe que ci-dessus, appliqué au reçu de paiement.
    Route::get('verification-versement/{versementId}/{signature}', [VerificationVersementController::class, 'show'])
        ->name('verification-versement.show');

    // `mot_de_passe` barre tout l'espace authentifié tant que le mot de passe
    // provisoire n'a pas été remplacé, à l'exception des routes qui permettent
    // justement d'en sortir (cf. ExigerMotDePasseRenouvele).
    Route::middleware(['auth:sanctum', 'mot_de_passe'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/mot-de-passe', [AuthController::class, 'changerMotDePasse'])->name('auth.mot-de-passe');
        Route::put('auth/profil', [AuthController::class, 'updateProfil'])->name('auth.profil');

        // Référentiel global, non scopé par établissement.
        Route::get('niveaux', [NiveauController::class, 'index'])->name('niveaux.index')->middleware('permission:niveaux.view');
        Route::post('niveaux', [NiveauController::class, 'store'])->name('niveaux.store')->middleware('permission:niveaux.manage');
        Route::get('niveaux/{id}', [NiveauController::class, 'show'])->name('niveaux.show')->middleware('permission:niveaux.view');
        Route::put('niveaux/{id}', [NiveauController::class, 'update'])->name('niveaux.update')->middleware('permission:niveaux.manage');
        Route::delete('niveaux/{id}', [NiveauController::class, 'destroy'])->name('niveaux.destroy')->middleware('permission:niveaux.manage');
        Route::post('niveaux/batch-delete', [NiveauController::class, 'batchDestroy'])->name('niveaux.batch-destroy')->middleware('permission:niveaux.manage');
        Route::post('niveaux/batch-update', [NiveauController::class, 'batchUpdate'])->name('niveaux.batch-update')->middleware('permission:niveaux.manage');

        // Toutes les routes métier (établissement, personnel, classes, élèves, ...)
        // sont scopées par établissement + niveau via le middleware `tenant`.
        // `idempotence` couvre d'un coup les 84 écritures métier : le mobile
        // peut rejouer n'importe laquelle sans risque de doublon, sans avoir
        // à déclarer route par route lesquelles sont rejouables.
        Route::middleware(['tenant', 'idempotence'])->group(function () {

            /*
             * Synchronisation de l'application mobile. Aucun privilège propre :
             * l'endpoint ne renvoie que les entités que l'utilisateur peut déjà
             * consulter, en filtrant lui-même sur ses privilèges (cf.
             * `RegistreSync`). Un privilège « sync.view » ne protégerait rien
             * de plus et créerait un second endroit où gérer les accès.
             */
            Route::get('sync', [SyncController::class, 'pull'])->name('sync.pull');
            Route::post('sync', [SyncController::class, 'push'])->name('sync.push');

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
            });

            Route::middleware('permission:personnel.view')->group(function () {
                Route::get('departements', [DepartementController::class, 'index'])->name('departements.index');
                Route::get('departements/{id}', [DepartementController::class, 'show'])->name('departements.show');
                Route::get('departements/{id}/statistiques/pedagogiques', [DepartementController::class, 'statsPedagogiques'])->name('departements.stats-pedagogiques');
                Route::get('departements/{id}/statistiques/pedagogiques/export-pdf', [DepartementController::class, 'exportPdfStatistiques'])->name('departements.export-pdf-stats');
                Route::get('fonctions-referentiel', [FonctionReferentielController::class, 'index'])->name('fonctions-referentiel.index');
                Route::get('fonctions-referentiel/{id}', [FonctionReferentielController::class, 'show'])->name('fonctions-referentiel.show');
                Route::get('personnels', [PersonnelController::class, 'index'])->name('personnels.index');
                Route::get('personnels/export', [PersonnelController::class, 'export'])->name('personnels.export');
                Route::get('personnels/fichier', [PersonnelController::class, 'fichier'])->name('personnels.fichier');
                // Route littérale avant le paramètre générique {id} ci-dessous, sinon
                // « identifiants » s'y ferait happer. Document sensible (mots de passe) :
                // exige `.manage` en plus du `.view` du groupe.
                Route::get('personnels/identifiants', [PersonnelController::class, 'identifiants'])
                    ->name('personnels.identifiants')->middleware('permission:personnel.manage');
                Route::get('personnels/{id}', [PersonnelController::class, 'show'])->name('personnels.show');
            });

            Route::middleware('permission:personnel.manage')->group(function () {
                Route::post('departements', [DepartementController::class, 'store'])->name('departements.store');
                Route::put('departements/{id}', [DepartementController::class, 'update'])->name('departements.update');
                Route::delete('departements/{id}', [DepartementController::class, 'destroy'])->name('departements.destroy');

                Route::post('fonctions-referentiel', [FonctionReferentielController::class, 'store'])->name('fonctions-referentiel.store');
                Route::put('fonctions-referentiel/{id}', [FonctionReferentielController::class, 'update'])->name('fonctions-referentiel.update');
                Route::delete('fonctions-referentiel/{id}', [FonctionReferentielController::class, 'destroy'])->name('fonctions-referentiel.destroy');
                Route::post('fonctions-referentiel/batch-delete', [FonctionReferentielController::class, 'batchDelete'])->name('fonctions-referentiel.batch-delete');

                Route::post('personnels', [PersonnelController::class, 'store'])->name('personnels.store');
                Route::put('personnels/{id}', [PersonnelController::class, 'update'])->name('personnels.update');
                Route::post('personnels/{id}/archive', [PersonnelController::class, 'archive'])->name('personnels.archive');
                Route::post('personnels/{id}/reactivate', [PersonnelController::class, 'reactivate'])->name('personnels.reactivate');
                Route::post('personnels/{id}/compte', [PersonnelController::class, 'createAccount'])->name('personnels.compte');
                Route::post('personnels/import', [PersonnelController::class, 'import'])->name('personnels.import');
                Route::get('personnels/{id}/attestation-employeur', [PersonnelController::class, 'attestationEmployeur'])->name('personnels.attestation');
                Route::delete('personnels/{id}', [PersonnelController::class, 'destroy'])->name('personnels.destroy');
                Route::post('personnels/batch-delete', [PersonnelController::class, 'batchDelete'])->name('personnels.batch-delete');
                Route::post('personnels/batch-fonction', [PersonnelController::class, 'batchFonction'])->name('personnels.batch-fonction');
            });

            Route::middleware('permission:dashboard.view')->group(function () {
                Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
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

            Route::middleware('permission:ecoles.manage')->group(function () {
                Route::get('annees-scolaires', [AnneeScolaireController::class, 'index'])->name('annees.index');
                Route::post('annees-scolaires', [AnneeScolaireController::class, 'store'])->name('annees.store');
                Route::post('annees-scolaires/{id}/activer', [AnneeScolaireController::class, 'activate'])->name('annees.activate');

                Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
                Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

                Route::get('ecole', [SchoolController::class, 'show'])->name('ecole.show');
                Route::put('ecole', [SchoolController::class, 'update'])->name('ecole.update');
                Route::post('ecole/images/{type}', [SchoolController::class, 'uploadImage'])->name('ecole.images.upload');
                Route::delete('ecole/images/{type}', [SchoolController::class, 'deleteImage'])->name('ecole.images.delete');
            });

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
                Route::get('ma-classe', [ClasseController::class, 'maClasse'])->name('classes.ma-classe');
                Route::get('classes/{id}/cartes-scolaires', [CarteScolaireController::class, 'classe'])->name('classes.cartes');
                Route::get('classes/{id}/eleves/pdf', [ListeElevesController::class, 'pdf'])->name('classes.eleves.pdf');
                Route::get('classes/{id}/eleves/word', [ListeElevesController::class, 'word'])->name('classes.eleves.word');
                Route::get('classes/{id}', [ClasseController::class, 'show'])->name('classes.show');
                Route::get('schools', [ClasseController::class, 'schools'])->name('schools.index');
                Route::get('schools/{id}', [ClasseController::class, 'showSchool'])->name('schools.show');
            });

            Route::middleware('permission:classes.manage')->group(function () {
                Route::post('classes', [ClasseController::class, 'store'])->name('classes.store');
                Route::post('classes/import', [ClasseController::class, 'import'])->name('classes.import');
                Route::put('classes/bulk-update', [ClasseController::class, 'bulkUpdate'])->name('classes.bulk-update');
                Route::put('classes/{id}', [ClasseController::class, 'update'])->name('classes.update');
                Route::delete('classes/{id}', [ClasseController::class, 'destroy'])->name('classes.destroy');

                Route::get('sous-systemes', [SousSystemeController::class, 'index'])->name('sous-systemes.index');
                Route::post('sous-systemes', [SousSystemeController::class, 'store'])->name('sous-systemes.store');
                Route::get('sous-systemes/{id}', [SousSystemeController::class, 'show'])->name('sous-systemes.show');
                Route::put('sous-systemes/{id}', [SousSystemeController::class, 'update'])->name('sous-systemes.update');
                Route::delete('sous-systemes/{id}', [SousSystemeController::class, 'destroy'])->name('sous-systemes.destroy');
            });

            Route::middleware('permission:eleves.view')->group(function () {
                // Photos DECC & OBC : réservées aux classes d'examen.
                Route::get('photos-examen/classes', [PhotoExamenController::class, 'classes'])->name('photos-examen.classes');
                Route::get('photos-examen/classes/{classeId}', [PhotoExamenController::class, 'candidats'])->name('photos-examen.candidats');
                Route::get('photos-examen/classes/{classeId}/archive', [PhotoExamenController::class, 'archive'])->name('photos-examen.archive');
                Route::get('eleves', [EleveController::class, 'index'])->name('eleves.index');
                Route::get('eleves/repartition', [EleveController::class, 'repartition'])->name('eleves.repartition');
                Route::get('eleves/export', [EleveController::class, 'export'])->name('eleves.export');
                Route::get('eleves/pdf', [ListeElevesController::class, 'pdfEcole'])->name('eleves.pdf');
                Route::get('eleves/recapitulatif-effectifs', [EleveRapportsController::class, 'recapitulatif'])->name('eleves.recapitulatif');
                Route::get('eleves/recapitulatif-effectifs/pdf', [EleveRapportsController::class, 'recapitulatifPdf'])->name('eleves.recapitulatif.pdf');
                Route::get('eleves/recapitulatif-sous-systemes', [EleveRapportsController::class, 'recapitulatifSousSystemes'])->name('eleves.recapitulatif-ss');
                Route::get('eleves/recapitulatif-sous-systemes/pdf', [EleveRapportsController::class, 'recapitulatifSousSystemesPdf'])->name('eleves.recapitulatif-ss.pdf');
                Route::get('eleves/tableau-ages', [EleveRapportsController::class, 'tableauAges'])->name('eleves.tableau-ages');
                Route::get('eleves/tableau-ages/pdf', [EleveRapportsController::class, 'tableauAgesPdf'])->name('eleves.tableau-ages.pdf');
                Route::get('eleves/{eleveId}/attestation-scolarite', [AttestationController::class, 'scolarite'])->name('eleves.attestation');
                Route::get('eleves/{id}', [EleveController::class, 'show'])->name('eleves.show');
            });

            Route::middleware('permission:eleves.manage')->group(function () {
                Route::post('eleves', [EleveController::class, 'store'])->name('eleves.store');
                Route::put('eleves/{id}', [EleveController::class, 'update'])->name('eleves.update');
                Route::delete('eleves/{id}', [EleveController::class, 'destroy'])->name('eleves.destroy');
                Route::post('eleves/batch-delete', [EleveController::class, 'batchDelete'])->name('eleves.batch-delete');
                Route::post('eleves/batch-transfert-classe', [EleveController::class, 'batchTransfertClasse'])->name('eleves.batch-transfert-classe');
                Route::post('eleves/batch-transfert-ecole', [EleveController::class, 'batchTransfertEcole'])->name('eleves.batch-transfert-ecole');
                Route::post('eleves/import', [EleveController::class, 'import'])->name('eleves.import');
                Route::get('eleves/import-progress/{token}', [EleveController::class, 'importProgress'])->name('eleves.import-progress');
                Route::post('eleves/import/preparer', [EleveController::class, 'importPreparer'])->name('eleves.import-preparer');
                Route::post('eleves/import/traiter/{token}', [EleveController::class, 'importerLot'])->name('eleves.import-traiter');
                Route::post('eleves/{id}/transfert', [EleveController::class, 'transfert'])->name('eleves.transfert');
                Route::post('eleves/{id}/photo', [EleveController::class, 'photo'])->name('eleves.photo');

                Route::get('tuteurs', [TuteurController::class, 'index'])->name('tuteurs.index');
                Route::get('tuteurs/identifiants/pdf', [TuteurController::class, 'identifiantsParentPdf'])->name('tuteurs.identifiants-pdf');
                Route::post('tuteurs/{id}/compte-parent', [TuteurController::class, 'creerCompteParent'])->name('tuteurs.compte-parent');
                Route::post('tuteurs/{id}/basculer-acces', [TuteurController::class, 'basculerAcces'])->name('tuteurs.basculer-acces');
                Route::delete('tuteurs/{id}/compte-parent', [TuteurController::class, 'supprimerCompteParent'])->name('tuteurs.supprimer-compte-parent');
                Route::post('tuteurs/{id}/supprimer-compte-parent', [TuteurController::class, 'supprimerCompteParent'])->name('tuteurs.supprimer-compte-parent-post');
                Route::post('tuteurs/comptes-parent-lot', [TuteurController::class, 'creerComptesParentLot'])->name('tuteurs.comptes-parent-lot');
                Route::post('tuteurs/comptes-parent-lot/preparer', [TuteurController::class, 'comptesParentLotPreparer'])->name('tuteurs.comptes-parent-lot-preparer');
                Route::post('tuteurs/comptes-parent-lot/traiter', [TuteurController::class, 'comptesParentLotTraiter'])->name('tuteurs.comptes-parent-lot-traiter');
                Route::delete('tuteurs/{id}', [TuteurController::class, 'destroy'])->name('tuteurs.destroy');
                Route::get('parent-usage-stats', [ParentUsageStatsController::class, 'index'])->name('parent-usage-stats.index');

                Route::get('preinscriptions', [PreinscriptionAdminController::class, 'index'])->name('preinscriptions.index');
                Route::get('preinscriptions/{id}', [PreinscriptionAdminController::class, 'show'])->name('preinscriptions.show');
                Route::put('preinscriptions/{id}', [PreinscriptionAdminController::class, 'update'])->name('preinscriptions.update');
                Route::post('preinscriptions/{id}/valider', [PreinscriptionAdminController::class, 'valider'])->name('preinscriptions.valider');
                Route::post('preinscriptions/{id}/rejeter', [PreinscriptionAdminController::class, 'rejeter'])->name('preinscriptions.rejeter');

                Route::get('modifications-eleves', [ModificationEleveAdminController::class, 'index'])->name('modifications-eleves.index');
                Route::get('modifications-eleves/{id}', [ModificationEleveAdminController::class, 'show'])->name('modifications-eleves.show');
                Route::post('modifications-eleves/{id}/valider', [ModificationEleveAdminController::class, 'valider'])->name('modifications-eleves.valider');
                Route::post('modifications-eleves/{id}/rejeter', [ModificationEleveAdminController::class, 'rejeter'])->name('modifications-eleves.rejeter');
            });

            /*
             * Portail parent. Gardé par le rôle et non par un privilège
             * `X.view` : ces routes ne rendent jamais qu'un sous-ensemble
             * borné aux propres enfants du compte (cf. `ParentAccess`), pas
             * une vue école entière — un privilège de lecture n'y aurait pas
             * le même sens que pour le personnel.
             */
            Route::prefix('parent')->name('parent.')->middleware('role:parent')->group(function () {
                Route::get('enfants', [ParentEspaceController::class, 'mesEnfants'])->name('enfants.index');
                Route::get('enfants/{eleveId}', [ParentEspaceController::class, 'enfant'])->name('enfants.show');
                Route::get('enfants/{eleveId}/finance', [ParentEspaceController::class, 'finance'])->name('enfants.finance');
                Route::get('enfants/{eleveId}/bulletin', [ParentEspaceController::class, 'bulletin'])->name('enfants.bulletin');
                Route::get('enfants/{eleveId}/progression', [ParentEspaceController::class, 'progression'])->name('enfants.progression');
                Route::get('enfants/{eleveId}/absences', [ParentEspaceController::class, 'absences'])->name('enfants.absences');
                Route::get('enfants/{eleveId}/emploi-du-temps', [ParentEspaceController::class, 'emploiDuTemps'])->name('enfants.emploi-du-temps');
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
            });

            /*
             * Espace enseignant : même principe que « mon-espace » ci-dessus,
             * étendu à la fiche personnel, la rémunération en lecture seule, et
             * à l'unique geste de gestion qu'un enseignant garde sur sa fiche de
             * progression (ajouter une évaluation) quand `pedagogie.manage` ne
             * lui est pas accordé. Le périmètre est vérifié dans le contrôleur,
             * pas ici : ce sont des routes sans `{classeId}` à borner.
             */
            Route::prefix('enseignant')->name('enseignant.')->group(function () {
                Route::get('mes-informations', [EnseignantController::class, 'mesInformations'])->name('mes-informations.show');
                Route::put('mes-informations', [EnseignantController::class, 'mettreAJourMesInformations'])->name('mes-informations.update');
                Route::get('remuneration', [EnseignantController::class, 'maRemuneration'])->name('remuneration.show');
                Route::get('mon-departement', [EnseignantController::class, 'monDepartement'])->name('mon-departement.show');
                Route::post('classe-matieres/{classeMatiereId}/evaluations', [EnseignantController::class, 'ajouterEvaluation'])->name('evaluations.store');
            });

            Route::middleware('permission:pedagogie.view')->group(function () {
                Route::get('matieres', [MatiereController::class, 'index'])->name('matieres.index');

                /*
                 * Compétences évaluées du primaire et de la maternelle : le
                 * référentiel qui porte les barèmes, et son attribution aux
                 * classes. Les matières en découlent.
                 */
                Route::get('competences', [CompetenceController::class, 'index'])->name('competences.index');
                // Référentiel d'appréciations de la maternelle : les niveaux
                // cochés à la saisie et coloriés sur le bulletin.
                Route::get('appreciations', [AppreciationController::class, 'index'])->name('appreciations.index');
                Route::get('classes/{classeId}/competences', [CompetenceController::class, 'parClasse'])->name('classes.competences.index');
                Route::get('matieres/export', [MatiereController::class, 'export'])->name('matieres.export');
                Route::get('matieres/{id}/classes', [MatiereController::class, 'classes'])->name('matieres.classes');
                Route::get('classes/{classeId}/matieres', [ClasseMatiereController::class, 'index'])->name('classes.matieres.index');
            });

            Route::middleware('permission:pedagogie.manage')->group(function () {
                Route::post('appreciations', [AppreciationController::class, 'store'])->name('appreciations.store');
                Route::put('appreciations/{id}', [AppreciationController::class, 'update'])->name('appreciations.update');
                Route::delete('appreciations/{id}', [AppreciationController::class, 'destroy'])->name('appreciations.destroy');

                Route::post('competences', [CompetenceController::class, 'store'])->name('competences.store');
                Route::put('competences/{id}', [CompetenceController::class, 'update'])->name('competences.update');
                Route::delete('competences/{id}', [CompetenceController::class, 'destroy'])->name('competences.destroy');
                Route::post('competences/batch-delete', [CompetenceController::class, 'batchDestroy'])->name('competences.batch-delete');
                Route::post('classes/{classeId}/competences', [CompetenceController::class, 'attribuer'])->name('classes.competences.attribuer');
                Route::put('classe-competences/{id}', [CompetenceController::class, 'modifierAttribution'])->name('classe-competences.update');
                Route::delete('classe-competences/{id}', [CompetenceController::class, 'retirer'])->name('classe-competences.destroy');

                Route::post('matieres', [MatiereController::class, 'store'])->name('matieres.store');
                Route::put('matieres/{id}', [MatiereController::class, 'update'])->name('matieres.update');
                Route::delete('matieres/{id}', [MatiereController::class, 'destroy'])->name('matieres.destroy');
                Route::post('matieres/batch-delete', [MatiereController::class, 'batchDestroy'])->name('matieres.batch-destroy');
                Route::post('matieres/import', [MatiereController::class, 'import'])->name('matieres.import');

                Route::post('classes/{classeId}/matieres', [ClasseMatiereController::class, 'store'])->name('classes.matieres.store');
                Route::put('classe-matieres/{id}', [ClasseMatiereController::class, 'update'])->name('classe-matieres.update');
                Route::delete('classe-matieres/{id}', [ClasseMatiereController::class, 'destroy'])->name('classe-matieres.destroy');
                Route::post('classe-matieres/copier', [ClasseMatiereController::class, 'copier'])->name('classe-matieres.copier');
                Route::post('classe-matieres/batch-enseignant', [ClasseMatiereController::class, 'batchEnseignant'])->name('classe-matieres.batch-enseignant');
            });

            /*
             * Progression pédagogique : le programme annuel se consulte avec la
             * pédagogie et ne s'édite qu'avec le droit de la gérer.
             */
            Route::middleware('permission:pedagogie.view')->group(function () {
                Route::get('progression', [ProgressionController::class, 'etablissement'])->name('progression.etablissement');
                Route::get('classes/{classeId}/progression', [ProgressionController::class, 'classe'])->name('progression.classe');
                Route::get('classe-matieres/{classeMatiereId}/progression', [ProgressionController::class, 'show'])->name('progression.show');
                Route::get('classe-matieres/{classeMatiereId}/progression/pdf', [ProgressionController::class, 'pdf'])->name('progression.pdf');
                Route::get('classe-matieres/{classeMatiereId}/progression-colonnes', [ProgressionController::class, 'colonnes'])->name('progression-colonnes.index');
                Route::get('classe-matieres/{classeMatiereId}/champs-personnalises', [ProgressionController::class, 'champs'])->name('champs-personnalises.index');
                Route::get('classe-matieres/{classeMatiereId}/evaluations', [EvaluationController::class, 'index'])->name('evaluations.index');
            });

            Route::middleware('permission:pedagogie.manage')->group(function () {
                Route::put('classe-matieres/{classeMatiereId}/progression', [ProgressionController::class, 'save'])->name('progression.save');
                Route::post('classe-matieres/{classeMatiereId}/progression/import', [ProgressionController::class, 'import'])->name('progression.import');
                Route::put('classe-matieres/{classeMatiereId}/progression/cartouche', [ProgressionController::class, 'enregistrerCartouche'])->name('progression.cartouche');
                Route::put('classe-matieres/{classeMatiereId}/progression-colonnes', [ProgressionController::class, 'enregistrerColonnes'])->name('progression-colonnes.save');
                Route::put('classe-matieres/{classeMatiereId}/champs-personnalises', [ProgressionController::class, 'enregistrerChamps'])->name('champs-personnalises.save');
                Route::post('classe-matieres/{classeMatiereId}/evaluations', [EvaluationController::class, 'store'])->name('evaluations.store');
                Route::put('evaluations/{id}', [EvaluationController::class, 'update'])->name('evaluations.update');
                Route::delete('evaluations/{id}', [EvaluationController::class, 'destroy'])->name('evaluations.destroy');
            });

            /*
             * « Ma journée » : déclarer les leçons traitées et faire l'appel.
             * Ouvert à qui peut pointer une classe — l'enseignant y est en outre
             * restreint à ses propres affectations par le service. Les routes
             * littérales (couverture, qr/{token}) précèdent le paramètre
             * générique {classeMatiereId} pour ne pas s'y faire happer.
             */
            Route::middleware('permission:appel.manage')->group(function () {
                Route::get('ma-journee/couverture', [MaJourneeController::class, 'couverture'])->name('ma-journee.couverture');
                Route::get('ma-journee/qr/{token}', [MaJourneeController::class, 'resoudreQr'])->name('ma-journee.qr');
                Route::get('ma-journee', [MaJourneeController::class, 'affectations'])->name('ma-journee.affectations');
                Route::get('ma-journee/{classeMatiereId}', [MaJourneeController::class, 'feuille'])->name('ma-journee.feuille');
                Route::post('ma-journee/{classeMatiereId}', [MaJourneeController::class, 'enregistrer'])->name('ma-journee.enregistrer');
            });

            Route::middleware('permission:pedagogie.view')->group(function () {
                Route::get('trimestres', [TrimestreController::class, 'index'])->name('trimestres.index');
            });

            Route::middleware('permission:ecoles.manage')->group(function () {
                Route::post('trimestres', [TrimestreController::class, 'store'])->name('trimestres.store');
                Route::post('trimestres/{id}/activer', [TrimestreController::class, 'activate'])->name('trimestres.activate');
            });

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

            Route::middleware('permission:pedagogie.manage')->group(function () {
                Route::post('niveaux-scolaires', [NiveauScolaireController::class, 'store'])->name('niveaux-scolaires.store');
                Route::put('niveaux-scolaires/{id}', [NiveauScolaireController::class, 'update'])->name('niveaux-scolaires.update');
                Route::delete('niveaux-scolaires/{id}', [NiveauScolaireController::class, 'destroy'])->name('niveaux-scolaires.destroy');
            });

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
                Route::get('classes/{classeId}/remplissage', [ResultatController::class, 'remplissage'])->name('resultats.remplissage');
                Route::get('classes/{classeId}/classement', [ResultatController::class, 'classement'])->name('resultats.classement');
                Route::get('classes/{classeId}/classement/export', [ResultatController::class, 'exportClassement'])->name('resultats.classement.export');
            });

            Route::middleware('permission:bulletins.view')->group(function () {
                Route::get('palmares', [ResultatController::class, 'palmares'])->name('resultats.palmares');
                Route::get('palmares/export', [ResultatController::class, 'exportPalmares'])->name('resultats.palmares.export');
                Route::get('palmares/pdf', [ResultatController::class, 'palmaresPdf'])->name('resultats.palmares.pdf');
                Route::get('eleves/{eleveId}/bulletin', [BulletinController::class, 'show'])->name('eleves.bulletin');
                Route::get('classes/{classeId}/bulletins', [BulletinController::class, 'classe'])->name('classes.bulletins');
                Route::get('classes/{classeId}/pv-conseil/pdf', [ResultatController::class, 'pvConseilPdf'])->name('resultats.pv-conseil.pdf');

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

            Route::middleware('permission:revendications.manage')->group(function () {
                Route::post('revendications', [RevendicationController::class, 'store'])->name('revendications.store');
                Route::put('revendications/{id}', [RevendicationController::class, 'update'])->name('revendications.update');
                Route::delete('revendications/{id}', [RevendicationController::class, 'destroy'])->name('revendications.destroy');
            });

            Route::middleware('permission:emploi_du_temps.view')->group(function () {
                Route::get('classes/{classeId}/emploi-du-temps', [EmploiDuTempsController::class, 'index'])->name('edt.index');
                Route::get('classes/{classeId}/seances', [SeanceController::class, 'index'])->name('seances.index');
                Route::get('seances/{id}/appel', [SeanceController::class, 'appel'])->name('seances.appel');
            });

            Route::middleware('permission:emploi_du_temps.manage')->group(function () {
                Route::post('classes/{classeId}/emploi-du-temps', [EmploiDuTempsController::class, 'store'])->name('edt.store');
                Route::put('classes/{classeId}/emploi-du-temps/{id}', [EmploiDuTempsController::class, 'update'])->name('edt.update');
                Route::delete('classes/{classeId}/emploi-du-temps/{id}', [EmploiDuTempsController::class, 'destroy'])->name('edt.destroy');
                Route::post('classes/{classeId}/emploi-du-temps/generer-seances', [EmploiDuTempsController::class, 'genererSeances'])->name('edt.generer');
                Route::post('classes/{classeId}/seances', [SeanceController::class, 'store'])->name('seances.store');
                Route::put('seances/{id}', [SeanceController::class, 'update'])->name('seances.update');
                Route::delete('seances/{id}', [SeanceController::class, 'destroy'])->name('seances.destroy');
            });

            Route::middleware('permission:appel.manage')->group(function () {
                Route::post('seances/{id}/appel', [SeanceController::class, 'enregistrerAppel'])->name('seances.appel.store');
            });

            Route::middleware('permission:discipline.view')->group(function () {
                Route::get('classes/{classeId}/absences', [AbsenceController::class, 'index'])->name('absences.index');
                Route::get('classes/{classeId}/bilan-disciplinaire', [AbsenceController::class, 'bilan'])->name('absences.bilan');
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
                Route::get('eleves/{eleveId}/scolarite', [ScolariteController::class, 'dossier'])->name('scolarite.dossier');
                Route::get('versements/{id}/recu', [ScolariteController::class, 'recu'])->name('scolarite.recu');

                Route::get('finance/insolvables', [InsolvablesController::class, 'index'])->name('finance.insolvables');
                Route::get('finance/insolvables/pdf', [InsolvablesController::class, 'pdf'])->name('finance.insolvables.pdf');

                Route::get('eleves/{eleveId}/moratoires', [MoratoireController::class, 'index'])->name('moratoires.index');
                Route::get('eleves/{eleveId}/remises', [RemiseController::class, 'index'])->name('remises.index');
                Route::get('eleves/{eleveId}/dettes-anterieures', [DetteAnterieureController::class, 'index'])->name('dettes-anterieures.index');
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
             * décider un montant — `finance.manage`, pas le simple encaissement.
             */
            Route::middleware('permission:finance.manage')->group(function () {
                Route::post('eleves/{eleveId}/moratoires', [MoratoireController::class, 'store'])->name('moratoires.store');
                Route::delete('moratoires/{id}', [MoratoireController::class, 'destroy'])->name('moratoires.destroy');

                Route::post('eleves/{eleveId}/remises', [RemiseController::class, 'store'])->name('remises.store');
                Route::delete('remises/{id}', [RemiseController::class, 'destroy'])->name('remises.destroy');

                Route::post('eleves/{eleveId}/dettes-anterieures', [DetteAnterieureController::class, 'store'])->name('dettes-anterieures.store');
                Route::delete('dettes-anterieures/{id}', [DetteAnterieureController::class, 'destroy'])->name('dettes-anterieures.destroy');
            });

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
                Route::post('depenses/{id}/payer', [DepenseController::class, 'payer'])->name('depenses.payer');
                Route::post('depenses/{id}/annuler', [DepenseController::class, 'annuler'])->name('depenses.annuler');
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
             * Tarifs : `finance.view` pour consulter la grille, `finance.manage`
             * pour la fixer — décider d'un prix n'est pas le métier du caissier.
             */
            Route::middleware('permission:finance.view')->group(function () {
                Route::get('tarifs', [TarifsController::class, 'index'])->name('tarifs.index');
                // Échéancier de la scolarité : le découpage de l'année en
                // tranches, lu par le portail parent et par les insolvables.
                Route::get('tranches-scolarite', [TrancheScolariteController::class, 'index'])->name('tranches-scolarite.index');
            });

            Route::middleware('permission:finance.manage')->group(function () {
                Route::put('tranches-scolarite', [TrancheScolariteController::class, 'remplacer'])->name('tranches-scolarite.remplacer');
                Route::post('tarifs', [TarifsController::class, 'definirTarif'])->name('tarifs.definir');
                Route::delete('tarifs/classes/{classeId}', [TarifsController::class, 'supprimerTarif'])->name('tarifs.supprimer');
                Route::post('tarifs/frais-annexes', [TarifsController::class, 'creerFraisAnnexe'])->name('tarifs.frais.store');
                Route::put('tarifs/frais-annexes/{id}', [TarifsController::class, 'modifierFraisAnnexe'])->name('tarifs.frais.update');
                Route::delete('tarifs/frais-annexes/{id}', [TarifsController::class, 'desactiverFraisAnnexe'])->name('tarifs.frais.destroy');
            });

            Route::middleware('permission:finance.rapports')->group(function () {
                Route::get('rapports/tableau-de-bord', [RapportFinancierController::class, 'tableauDeBord'])->name('rapports.bord');
                Route::get('rapports/resultat', [RapportFinancierController::class, 'resultat'])->name('rapports.resultat');
                Route::get('rapports/tresorerie', [RapportFinancierController::class, 'tresorerie'])->name('rapports.tresorerie');
                Route::get('rapports/balance', [RapportFinancierController::class, 'balance'])->name('rapports.balance');
            });

            /*
             * Finances — paie. Un seul privilège : préparer, arrêter et régler
             * la paie forment une même responsabilité, et personne ne prépare
             * un bulletin sans pouvoir le mener au bout.
             */
            Route::middleware('permission:finance.paie')->group(function () {
                Route::get('remunerations', [RemunerationController::class, 'index'])->name('remunerations.index');
                Route::get('remunerations/{personnelId}/historique', [RemunerationController::class, 'historique'])->name('remunerations.historique');
                Route::get('remunerations/{personnelId}/anciennete', [RemunerationController::class, 'anciennete'])->name('remunerations.anciennete');
                Route::post('remunerations/appliquer', [RemunerationController::class, 'appliquer'])->name('remunerations.appliquer');
                Route::post('remunerations/simuler', [RemunerationController::class, 'simuler'])->name('remunerations.simuler');
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

            Route::middleware('permission:discipline.manage')->group(function () {
                Route::post('classes/{classeId}/absences', [AbsenceController::class, 'bulkStore'])->name('absences.bulk-store');
                Route::post('sanctions', [SanctionController::class, 'store'])->name('sanctions.store');
                Route::put('sanctions/{id}', [SanctionController::class, 'update'])->name('sanctions.update');
                Route::delete('sanctions/{id}', [SanctionController::class, 'destroy'])->name('sanctions.destroy');
            });

            Route::middleware('permission:infirmerie.view')->group(function () {
                Route::get('infirmerie/visites', [VisiteInfirmerieController::class, 'index'])->name('infirmerie.visites.index');
                Route::get('infirmerie/malaises', [MalaiseReferentielController::class, 'index'])->name('infirmerie.malaises.index');
            });

            Route::middleware('permission:infirmerie.manage')->group(function () {
                Route::post('infirmerie/visites', [VisiteInfirmerieController::class, 'store'])->name('infirmerie.visites.store');
                Route::put('infirmerie/visites/{id}', [VisiteInfirmerieController::class, 'update'])->name('infirmerie.visites.update');
                Route::delete('infirmerie/visites/{id}', [VisiteInfirmerieController::class, 'destroy'])->name('infirmerie.visites.destroy');
                Route::post('infirmerie/malaises', [MalaiseReferentielController::class, 'store'])->name('infirmerie.malaises.store');
                Route::put('infirmerie/malaises/{id}', [MalaiseReferentielController::class, 'update'])->name('infirmerie.malaises.update');
                Route::delete('infirmerie/malaises/{id}', [MalaiseReferentielController::class, 'destroy'])->name('infirmerie.malaises.destroy');
            });

            Route::middleware('permission:bus.view')->group(function () {
                Route::get('bus/vehicules', [BusVehiculeController::class, 'index'])->name('bus.vehicules.index');
                Route::get('bus/vehicules/{id}/eleves/pdf', [BusVehiculeController::class, 'elevesPdf'])->name('bus.vehicules.eleves-pdf');
                Route::get('bus/vehicules/{id}/bilan/pdf', [BusVehiculeController::class, 'bilanPdf'])->name('bus.vehicules.bilan-pdf');
                Route::get('bus/trajets', [BusTrajetController::class, 'index'])->name('bus.trajets.index');
                Route::get('bus/trajets/{id}', [BusTrajetController::class, 'show'])->name('bus.trajets.show');
                Route::get('bus/affectations', [BusAffectationController::class, 'index'])->name('bus.affectations.index');
                Route::get('bus/eleves', [BusAffectationController::class, 'eleves'])->name('bus.eleves');
            });

            Route::middleware('permission:bus.manage')->group(function () {
                Route::post('bus/vehicules', [BusVehiculeController::class, 'store'])->name('bus.vehicules.store');
                Route::put('bus/vehicules/{id}', [BusVehiculeController::class, 'update'])->name('bus.vehicules.update');
                Route::delete('bus/vehicules/{id}', [BusVehiculeController::class, 'destroy'])->name('bus.vehicules.destroy');

                Route::post('bus/trajets', [BusTrajetController::class, 'store'])->name('bus.trajets.store');
                Route::put('bus/trajets/{id}', [BusTrajetController::class, 'update'])->name('bus.trajets.update');
                Route::delete('bus/trajets/{id}', [BusTrajetController::class, 'destroy'])->name('bus.trajets.destroy');
                Route::post('bus/trajets/{id}/notifier', [BusTrajetController::class, 'notifier'])->name('bus.trajets.notifier');
                Route::post('bus/trajets/{trajetId}/arrets', [BusTrajetController::class, 'ajouterArret'])->name('bus.arrets.store');
                Route::put('bus/trajets/{trajetId}/arrets/{arretId}', [BusTrajetController::class, 'modifierArret'])->name('bus.arrets.update');
                Route::delete('bus/trajets/{trajetId}/arrets/{arretId}', [BusTrajetController::class, 'supprimerArret'])->name('bus.arrets.destroy');

                Route::post('bus/affectations', [BusAffectationController::class, 'store'])->name('bus.affectations.store');
                Route::post('bus/souscriptions-lot', [BusAffectationController::class, 'souscrireLot'])->name('bus.affectations.souscrire-lot');
                Route::put('bus/affectations/{id}', [BusAffectationController::class, 'update'])->name('bus.affectations.update');
                Route::delete('bus/affectations/{id}', [BusAffectationController::class, 'destroy'])->name('bus.affectations.destroy');
            });

            Route::middleware('permission:inventaire.view')->group(function () {
                Route::get('inventaire', [InventaireController::class, 'index'])->name('inventaire.index');
            });
            Route::middleware('permission:inventaire.manage')->group(function () {
                Route::post('inventaire', [InventaireController::class, 'store'])->name('inventaire.store');
                // Déclarées avant « inventaire/{id} » : sans cela, « etiquettes »
                // serait capté comme un identifiant d'article.
                Route::post('inventaire/etiquettes', [InventaireController::class, 'etiquettes'])->name('inventaire.etiquettes');
                Route::post('inventaire/{id}/code-barre', [InventaireController::class, 'codeBarre'])->name('inventaire.code-barre');
                Route::put('inventaire/{id}', [InventaireController::class, 'update'])->name('inventaire.update');
                Route::delete('inventaire/{id}', [InventaireController::class, 'destroy'])->name('inventaire.destroy');
            });

            /*
             * Point de vente des fournitures. Le catalogue et le stock sont
             * ceux de l'inventaire : ce groupe n'ouvre qu'une caisse par-dessus.
             */
            Route::middleware('permission:point_de_vente.view')->group(function () {
                Route::get('point-de-vente/catalogue', [PointDeVenteController::class, 'catalogue'])->name('point-de-vente.catalogue');
                Route::get('point-de-vente/articles/{code}', [PointDeVenteController::class, 'parCodeBarre'])->name('point-de-vente.article-code-barre');
                Route::get('point-de-vente/ventes', [PointDeVenteController::class, 'ventes'])->name('point-de-vente.ventes');
                Route::get('point-de-vente/ventes/{id}/facture', [PointDeVenteController::class, 'facture'])->name('point-de-vente.facture');
                Route::get('point-de-vente/entrees', [PointDeVenteController::class, 'entrees'])->name('point-de-vente.entrees');
            });
            Route::middleware('permission:point_de_vente.vendre')->group(function () {
                Route::post('point-de-vente/ventes', [PointDeVenteController::class, 'vendre'])->name('point-de-vente.vendre');
            });
            Route::middleware('permission:point_de_vente.manage')->group(function () {
                Route::post('point-de-vente/ventes/{id}/annuler', [PointDeVenteController::class, 'annulerVente'])->name('point-de-vente.annuler');
                Route::post('point-de-vente/entrees', [PointDeVenteController::class, 'entrer'])->name('point-de-vente.entrer');
            });
        });
    });
});
