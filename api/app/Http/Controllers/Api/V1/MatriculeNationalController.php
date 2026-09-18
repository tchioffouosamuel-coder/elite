<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\MatriculeNationalExport;
use App\Exports\MatriculeNationalModeleExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Imports\MatriculeNationalImport;
use App\Models\ActivityLog;
use App\Models\Eleve;
use App\Models\School;
use App\Services\MatriculeNationalService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Gestion du matricule national des élèves du secondaire (cf.
 * `Eleve::matricule_national`, réservé à ce cycle — cf.
 * `School::estSecondaire()`) : liste, recherche, modification/suppression
 * (par la mise à jour élève déjà en place), et import/export en masse pour
 * la campagne annuelle de saisie transmise par le ministère.
 *
 * Distincte de {@see rechercher()} : celle-ci interroge cartescolaire.cm
 * pour UN élève à la fois (aide à la saisie, déjà branchée sur les écrans
 * d'inscription) ; celle-ci gère la liste déjà enregistrée dans l'école.
 */
class MatriculeNationalController extends Controller
{
    public function __construct(private readonly MatriculeNationalService $service) {}

    /**
     * Recherche du matricule national d'un élève sur cartescolaire.cm —
     * réservée au secondaire, seul cycle où cartescolaire.cm référence les
     * établissements (cf. School::estSecondaire()).
     */
    public function rechercher(Request $request): JsonResponse
    {
        $school = School::findOrFail(app('tenant.school_id'));

        if (! $school->estSecondaire()) {
            return ApiResponse::error("La recherche de matricule national n'est disponible qu'au secondaire.", 422);
        }

        if (! $school->national_school_code) {
            return ApiResponse::error("Le code national de l'établissement n'est pas configuré (Paramètres > École).", 422);
        }

        $data = $request->validate([
            'student_name' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $students = $this->service->fetchStudents($data['student_name'], $school->national_school_code);

        return ApiResponse::success($students);
    }

    /**
     * Liste des élèves du secondaire — matricule national renseigné ou non,
     * cf. `?rempli=1` pour ne montrer que les manquants. Recherche par nom,
     * matricule interne ou matricule national, tous confondus.
     */
    public function index(Request $request): JsonResponse
    {
        $recherche = $request->string('search')->toString();
        $rempli = $request->query('rempli');

        $eleves = Eleve::forSchool(Tenant::schoolIds())
            ->whereHas('school', fn ($q) => $q->where('type', 'secondaire'))
            ->where('statut', 'actif')
            ->with(['classe:id,nom', 'school:id,name'])
            ->when($recherche !== '', fn ($q) => $q->where(
                fn ($q2) => $q2->where('nom_complet', 'like', "%{$recherche}%")
                    ->orWhere('matricule', 'like', "%{$recherche}%")
                    ->orWhere('matricule_national', 'like', "%{$recherche}%"),
            ))
            ->when($rempli === '1', fn ($q) => $q->whereNotNull('matricule_national'))
            ->when($rempli === '0', fn ($q) => $q->whereNull('matricule_national'))
            ->orderBy('nom_complet')
            ->paginate((int) $request->integer('per_page', 50));

        return ApiResponse::paginated($eleves->through(fn (Eleve $e) => [
            'id' => $e->id,
            'matricule' => $e->matricule,
            'matricule_national' => $e->matricule_national,
            'nom_complet' => $e->nom_complet,
            'classe' => $e->classe?->nom,
            'school' => $e->school?->name,
        ]));
    }

    /**
     * Fixe ou efface (valeur vide) le matricule national d'un élève —
     * réutilise les mêmes contraintes que `EleveController::update()`
     * (secondaire uniquement, unicité) plutôt que de les dupliquer.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->findOrFail($id);

        if (! $eleve->school->estSecondaire()) {
            return ApiResponse::error('Le matricule national est réservé aux élèves du secondaire.', 422);
        }

        $data = $request->validate([
            'matricule_national' => [
                'nullable', 'string', 'max:50',
                Rule::unique('eleves', 'matricule_national')->ignore($eleve->id),
            ],
        ]);

        $eleve->update(['matricule_national' => $data['matricule_national'] ?: null]);

        ActivityLog::enregistrer(
            $request->user(),
            'matricule_national.maj',
            $data['matricule_national']
                ? "Matricule national de {$eleve->nom_complet} fixé à {$data['matricule_national']}."
                : "Matricule national de {$eleve->nom_complet} effacé.",
            $eleve,
        );

        return ApiResponse::success([
            'id' => $eleve->id, 'matricule_national' => $eleve->matricule_national,
        ], 'Matricule national mis à jour.');
    }

    /** Liste complète, matricule national renseigné ou non. */
    public function export(): BinaryFileResponse
    {
        return Excel::download(new MatriculeNationalExport(Tenant::schoolIds()), 'matricules-nationaux.xlsx');
    }

    /**
     * Modèle « à remplir » : chaque élève du secondaire sans matricule
     * national encore renseigné, une ligne chacun, prêt à compléter puis
     * réimporter — pas un simple gabarit d'en-têtes vides comme
     * `ModeleGenerique` : sans les identifiants déjà en clair sur chaque
     * ligne, quiconque remplit le fichier n'aurait aucun moyen de savoir à
     * quel élève chaque matricule doit revenir.
     */
    public function modele(): BinaryFileResponse
    {
        return Excel::download(new MatriculeNationalModeleExport(Tenant::schoolIds()), 'modele-matricules-nationaux.xlsx');
    }

    /** Importe le fichier complété : rapproche chaque ligne par le matricule interne (colonne IDEleves), jamais créé de nouvel élève. */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $schoolId = Tenant::schoolId();
        $import = new MatriculeNationalImport($schoolId, $request->user()->id);
        Excel::import($import, $request->file('file'));

        return ApiResponse::success([
            'imported' => $import->importees,
            'failed' => count($import->erreurs),
            'erreurs' => $import->erreurs,
        ], "{$import->importees} matricule(s) national(aux) mis à jour.");
    }
}
