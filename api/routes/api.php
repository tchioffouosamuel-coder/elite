<?php

use App\Http\Controllers\Api\V1\AbsenceController;
use App\Http\Controllers\Api\V1\AnneeScolaireController;
use App\Http\Controllers\Api\V1\AttestationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BulletinController;
use App\Http\Controllers\Api\V1\CarteScolaireController;
use App\Http\Controllers\Api\V1\ClasseController;
use App\Http\Controllers\Api\V1\ClasseMatiereController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DepartementController;
use App\Http\Controllers\Api\V1\EleveController;
use App\Http\Controllers\Api\V1\MatiereController;
use App\Http\Controllers\Api\V1\NiveauController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\PersonnelController;
use App\Http\Controllers\Api\V1\ResultatController;
use App\Http\Controllers\Api\V1\SanctionController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\TrimestreController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // Référentiel global, non scopé par établissement.
        Route::get('niveaux', [NiveauController::class, 'index'])->name('niveaux.index');

        // Toutes les routes métier (établissement, personnel, classes, élèves, ...)
        // sont scopées par établissement + niveau via le middleware `tenant`.
        Route::middleware('tenant')->group(function () {

            Route::middleware('permission:personnel.view')->group(function () {
                Route::get('departements', [DepartementController::class, 'index'])->name('departements.index');
                Route::get('personnels', [PersonnelController::class, 'index'])->name('personnels.index');
                Route::get('personnels/export', [PersonnelController::class, 'export'])->name('personnels.export');
                Route::get('personnels/{id}', [PersonnelController::class, 'show'])->name('personnels.show');
            });

            Route::middleware('permission:personnel.manage')->group(function () {
                Route::post('departements', [DepartementController::class, 'store'])->name('departements.store');
                Route::put('departements/{id}', [DepartementController::class, 'update'])->name('departements.update');
                Route::delete('departements/{id}', [DepartementController::class, 'destroy'])->name('departements.destroy');

                Route::post('personnels', [PersonnelController::class, 'store'])->name('personnels.store');
                Route::put('personnels/{id}', [PersonnelController::class, 'update'])->name('personnels.update');
                Route::post('personnels/{id}/archive', [PersonnelController::class, 'archive'])->name('personnels.archive');
                Route::post('personnels/{id}/reactivate', [PersonnelController::class, 'reactivate'])->name('personnels.reactivate');
                Route::post('personnels/{id}/compte', [PersonnelController::class, 'createAccount'])->name('personnels.compte');
                Route::post('personnels/import', [PersonnelController::class, 'import'])->name('personnels.import');
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
            });

            Route::middleware('permission:classes.view')->group(function () {
                Route::get('classes', [ClasseController::class, 'index'])->name('classes.index');
                Route::get('classes/{id}', [ClasseController::class, 'show'])->name('classes.show');
            });

            Route::middleware('permission:classes.manage')->group(function () {
                Route::post('classes', [ClasseController::class, 'store'])->name('classes.store');
                Route::put('classes/{id}', [ClasseController::class, 'update'])->name('classes.update');
                Route::delete('classes/{id}', [ClasseController::class, 'destroy'])->name('classes.destroy');
            });

            Route::middleware('permission:eleves.view')->group(function () {
                Route::get('eleves', [EleveController::class, 'index'])->name('eleves.index');
                Route::get('eleves/repartition', [EleveController::class, 'repartition'])->name('eleves.repartition');
                Route::get('eleves/export', [EleveController::class, 'export'])->name('eleves.export');
                Route::get('eleves/{eleveId}/attestation-scolarite', [AttestationController::class, 'scolarite'])->name('eleves.attestation');
                Route::get('eleves/{eleveId}/carte-scolaire', [CarteScolaireController::class, 'show'])->name('eleves.carte');
                Route::get('eleves/{id}', [EleveController::class, 'show'])->name('eleves.show');
            });

            Route::middleware('permission:eleves.manage')->group(function () {
                Route::post('eleves', [EleveController::class, 'store'])->name('eleves.store');
                Route::put('eleves/{id}', [EleveController::class, 'update'])->name('eleves.update');
                Route::post('eleves/import', [EleveController::class, 'import'])->name('eleves.import');
            });

            Route::middleware('permission:pedagogie.view')->group(function () {
                Route::get('matieres', [MatiereController::class, 'index'])->name('matieres.index');
                Route::get('classes/{classeId}/matieres', [ClasseMatiereController::class, 'index'])->name('classes.matieres.index');
            });

            Route::middleware('permission:pedagogie.manage')->group(function () {
                Route::post('matieres', [MatiereController::class, 'store'])->name('matieres.store');
                Route::put('matieres/{id}', [MatiereController::class, 'update'])->name('matieres.update');
                Route::delete('matieres/{id}', [MatiereController::class, 'destroy'])->name('matieres.destroy');

                Route::post('classes/{classeId}/matieres', [ClasseMatiereController::class, 'store'])->name('classes.matieres.store');
                Route::put('classe-matieres/{id}', [ClasseMatiereController::class, 'update'])->name('classe-matieres.update');
                Route::delete('classe-matieres/{id}', [ClasseMatiereController::class, 'destroy'])->name('classe-matieres.destroy');
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
            });

            Route::middleware('permission:discipline.view')->group(function () {
                Route::get('classes/{classeId}/absences', [AbsenceController::class, 'index'])->name('absences.index');
                Route::get('classes/{classeId}/bilan-disciplinaire', [AbsenceController::class, 'bilan'])->name('absences.bilan');
                Route::get('classes/{classeId}/bilan-disciplinaire/pdf', [AbsenceController::class, 'bilanPdf'])->name('absences.bilan.pdf');
                Route::get('sanctions', [SanctionController::class, 'index'])->name('sanctions.index');
            });

            Route::middleware('permission:discipline.manage')->group(function () {
                Route::post('classes/{classeId}/absences', [AbsenceController::class, 'bulkStore'])->name('absences.bulk-store');
                Route::post('sanctions', [SanctionController::class, 'store'])->name('sanctions.store');
                Route::delete('sanctions/{id}', [SanctionController::class, 'destroy'])->name('sanctions.destroy');
            });
        });
    });
});
