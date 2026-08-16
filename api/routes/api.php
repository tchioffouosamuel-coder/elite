<?php

use App\Http\Controllers\Api\V1\AbsenceController;
use App\Http\Controllers\Api\V1\AnneeScolaireController;
use App\Http\Controllers\Api\V1\AttestationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BulletinController;
use App\Http\Controllers\Api\V1\BulletinPrimaireController;
use App\Http\Controllers\Api\V1\CarteScolaireController;
use App\Http\Controllers\Api\V1\ClasseController;
use App\Http\Controllers\Api\V1\ClasseMatiereController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DepartementController;
use App\Http\Controllers\Api\V1\DepenseController;
use App\Http\Controllers\Api\V1\EleveController;
use App\Http\Controllers\Api\V1\EmploiDuTempsController;
use App\Http\Controllers\Api\V1\FonctionReferentielController;
use App\Http\Controllers\Api\V1\ListeElevesController;
use App\Http\Controllers\Api\V1\MaJourneeController;
use App\Http\Controllers\Api\V1\MatiereController;
use App\Http\Controllers\Api\V1\NiveauController;
use App\Http\Controllers\Api\V1\NiveauScolaireController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\NotePrimaireController;
use App\Http\Controllers\Api\V1\PaieController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\PersonnelController;
use App\Http\Controllers\Api\V1\PhotoExamenController;
use App\Http\Controllers\Api\V1\ProgressionController;
use App\Http\Controllers\Api\V1\RapportFinancierController;
use App\Http\Controllers\Api\V1\RemunerationController;
use App\Http\Controllers\Api\V1\ResultatController;
use App\Http\Controllers\Api\V1\ResultatPrimaireController;
use App\Http\Controllers\Api\V1\SanctionController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\ScolariteController;
use App\Http\Controllers\Api\V1\SeanceController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\SousSystemeController;
use App\Http\Controllers\Api\V1\StatistiqueController;
use App\Http\Controllers\Api\V1\TarifsController;
use App\Http\Controllers\Api\V1\TrimestreController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    // `mot_de_passe` barre tout l'espace authentifié tant que le mot de passe
    // provisoire n'a pas été remplacé, à l'exception des routes qui permettent
    // justement d'en sortir (cf. ExigerMotDePasseRenouvele).
    Route::middleware(['auth:sanctum', 'mot_de_passe'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/mot-de-passe', [AuthController::class, 'changerMotDePasse'])->name('auth.mot-de-passe');

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
        Route::middleware('tenant')->group(function () {

            /*
             * Administration des privilèges. Protégée par le rôle et non par un
             * privilège : un droit qui permettrait de s'octroyer tous les
             * autres ne protégerait rien.
             */
            Route::middleware('super_admin')->group(function () {
                Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
                Route::get('fonctions-referentiel/{id}/permissions', [PermissionController::class, 'show'])->name('permissions.show');
                Route::put('fonctions-referentiel/{id}/permissions', [PermissionController::class, 'update'])->name('permissions.update');
            });

            Route::middleware('permission:personnel.view')->group(function () {
                Route::get('departements', [DepartementController::class, 'index'])->name('departements.index');
                Route::get('fonctions-referentiel', [FonctionReferentielController::class, 'index'])->name('fonctions-referentiel.index');
                Route::get('fonctions-referentiel/{id}', [FonctionReferentielController::class, 'show'])->name('fonctions-referentiel.show');
                Route::get('personnels', [PersonnelController::class, 'index'])->name('personnels.index');
                Route::get('personnels/export', [PersonnelController::class, 'export'])->name('personnels.export');
                Route::get('personnels/fichier', [PersonnelController::class, 'fichier'])->name('personnels.fichier');
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
            });

            Route::middleware('permission:dashboard.view')->group(function () {
                Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
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
                Route::post('eleves/{id}/transfert', [EleveController::class, 'transfert'])->name('eleves.transfert');
                Route::post('eleves/{id}/photo', [EleveController::class, 'photo'])->name('eleves.photo');
            });

            Route::middleware('permission:pedagogie.view')->group(function () {
                Route::get('matieres', [MatiereController::class, 'index'])->name('matieres.index');
                Route::get('classes/{classeId}/matieres', [ClasseMatiereController::class, 'index'])->name('classes.matieres.index');
            });

            Route::middleware('permission:pedagogie.manage')->group(function () {
                Route::post('matieres', [MatiereController::class, 'store'])->name('matieres.store');
                Route::put('matieres/{id}', [MatiereController::class, 'update'])->name('matieres.update');
                Route::delete('matieres/{id}', [MatiereController::class, 'destroy'])->name('matieres.destroy');
                Route::post('matieres/batch-delete', [MatiereController::class, 'batchDestroy'])->name('matieres.batch-destroy');
                Route::post('matieres/import', [MatiereController::class, 'import'])->name('matieres.import');

                Route::post('classes/{classeId}/matieres', [ClasseMatiereController::class, 'store'])->name('classes.matieres.store');
                Route::put('classe-matieres/{id}', [ClasseMatiereController::class, 'update'])->name('classe-matieres.update');
                Route::delete('classe-matieres/{id}', [ClasseMatiereController::class, 'destroy'])->name('classe-matieres.destroy');
            });

            /*
             * Progression pédagogique : le programme annuel se consulte avec la
             * pédagogie et ne s'édite qu'avec le droit de la gérer.
             */
            Route::middleware('permission:pedagogie.view')->group(function () {
                Route::get('progression', [ProgressionController::class, 'etablissement'])->name('progression.etablissement');
                Route::get('classes/{classeId}/progression', [ProgressionController::class, 'classe'])->name('progression.classe');
                Route::get('classe-matieres/{classeMatiereId}/progression', [ProgressionController::class, 'show'])->name('progression.show');
            });

            Route::middleware('permission:pedagogie.manage')->group(function () {
                Route::put('classe-matieres/{classeMatiereId}/progression', [ProgressionController::class, 'save'])->name('progression.save');
            });

            /*
             * « Ma journée » : déclarer les leçons traitées et faire l'appel.
             * Ouvert à qui peut pointer une classe — l'enseignant y est en outre
             * restreint à ses propres affectations par le service.
             */
            Route::middleware('permission:appel.manage')->group(function () {
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
                Route::get('classe-matieres/{classeMatiereId}/notes-primaire', [NotePrimaireController::class, 'index'])->name('notes-primaire.index');
            });

            Route::middleware('permission:notes.create')->group(function () {
                Route::post('classe-matieres/{classeMatiereId}/notes-primaire', [NotePrimaireController::class, 'bulkStore'])->name('notes-primaire.bulk-store');
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

                // Statistiques globales de l'établissement (équivalent des pages
                // generate_*_stats_batch_advanced.php de _smapp).
                Route::get('statistiques/pedagogiques', [StatistiqueController::class, 'pedagogiques'])->name('statistiques.pedagogiques');
                Route::get('statistiques/pedagogiques/pdf', [StatistiqueController::class, 'pedagogiquesPdf'])->name('statistiques.pedagogiques.pdf');
                Route::get('statistiques/disciplinaires', [StatistiqueController::class, 'disciplinaires'])->name('statistiques.disciplinaires');
                Route::get('statistiques/disciplinaires/pdf', [StatistiqueController::class, 'disciplinairesPdf'])->name('statistiques.disciplinaires.pdf');
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
                Route::get('sanctions', [SanctionController::class, 'index'])->name('sanctions.index');
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
            });

            Route::middleware('permission:finance.encaisser')->group(function () {
                Route::post('scolarite/dossiers/{id}/versements', [ScolariteController::class, 'encaisser'])->name('scolarite.encaisser');
            });

            Route::middleware('permission:finance.annuler')->group(function () {
                Route::post('versements/{id}/annuler', [ScolariteController::class, 'annuler'])->name('scolarite.annuler');
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
            });

            /*
             * Tarifs : `finance.view` pour consulter la grille, `finance.manage`
             * pour la fixer — décider d'un prix n'est pas le métier du caissier.
             */
            Route::middleware('permission:finance.view')->group(function () {
                Route::get('tarifs', [TarifsController::class, 'index'])->name('tarifs.index');
            });

            Route::middleware('permission:finance.manage')->group(function () {
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
                Route::post('paie/preparer', [PaieController::class, 'preparerLot'])->name('paie.preparer-lot');
                Route::post('paie/personnels/{personnelId}/preparer', [PaieController::class, 'preparer'])->name('paie.preparer');
                Route::get('paie/bulletins/{id}/pdf', [PaieController::class, 'bulletinPdf'])->name('paie.bulletin-pdf');
                Route::post('paie/bulletins/arreter-lot', [PaieController::class, 'arreterLot'])->name('paie.arreter-lot');
                Route::post('paie/bulletins/{id}/arreter', [PaieController::class, 'arreter'])->name('paie.arreter');
                Route::post('paie/bulletins/payer-lot', [PaieController::class, 'payerLot'])->name('paie.payer-lot');
                Route::post('paie/bulletins/{id}/payer', [PaieController::class, 'payer'])->name('paie.payer');
                Route::post('paie/bulletins/{id}/emarger', [PaieController::class, 'emarger'])->name('paie.emarger');
            });

            Route::middleware('permission:discipline.manage')->group(function () {
                Route::post('classes/{classeId}/absences', [AbsenceController::class, 'bulkStore'])->name('absences.bulk-store');
                Route::post('sanctions', [SanctionController::class, 'store'])->name('sanctions.store');
                Route::delete('sanctions/{id}', [SanctionController::class, 'destroy'])->name('sanctions.destroy');
            });
        });
    });
});
