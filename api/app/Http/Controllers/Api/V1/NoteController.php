<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\NoteExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkSaveNotesRequest;
use App\Models\ClasseMatiere;
use App\Models\Sequence;
use App\Services\NoteService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NoteController extends Controller
{
    public function __construct(private readonly NoteService $service) {}

    public function index(Request $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = ClasseMatiere::forSchool(Tenant::schoolIds())->with('classe')->findOrFail($classeMatiereId);
        $sequenceId = $request->integer('sequence_id');

        if (! $sequenceId) {
            return ApiResponse::error('Le paramètre sequence_id est requis.', 422);
        }

        return ApiResponse::success($this->service->grille($classeMatiere, $sequenceId)->values());
    }

    public function bulkStore(BulkSaveNotesRequest $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = ClasseMatiere::forSchool(Tenant::schoolIds())->with('classe')->findOrFail($classeMatiereId);

        if (! $this->service->peutSaisir($request->user(), $classeMatiere)) {
            return ApiResponse::forbidden("Vous n'êtes pas l'enseignant assigné à cette matière pour cette classe.");
        }

        // Un enseignant ne note que le trimestre actif ; la direction peut
        // encore corriger un trimestre clos (cf. User::peutSaisirHorsTrimestreActif()).
        if (! $request->user()->peutSaisirHorsTrimestreActif()) {
            $sequence = Sequence::with('trimestre')->find($request->integer('sequence_id'));

            if (! $sequence?->trimestre?->is_active) {
                return ApiResponse::forbidden("Ce trimestre n'est plus actif : seule la direction peut encore y modifier des notes.");
            }
        }

        $count = $this->service->sauvegarderEnLot(
            $classeMatiere,
            $request->integer('sequence_id'),
            $request->input('notes'),
            $request->user(),
        );

        return ApiResponse::success(['saved' => $count], "{$count} note(s) enregistrée(s).");
    }

    public function import(Request $request, int $classeMatiereId): JsonResponse
    {
        // Le stockage de l'import (NoteImport) exige une école unique ; la
        // consultation, elle, doit voir tout le périmètre agrégé du compte
        // — c'est ce qui distingue `Tenant::schoolId()` de `schoolIds()` ici.
        $classeMatiere = ClasseMatiere::forSchool(Tenant::schoolIds())->with('classe')->findOrFail($classeMatiereId);

        if (! $this->service->peutSaisir($request->user(), $classeMatiere)) {
            return ApiResponse::forbidden("Vous n'êtes pas l'enseignant assigné à cette matière pour cette classe.");
        }

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv'], 'sequence_id' => ['required', 'integer']]);

        $sequence = Sequence::whereHas(
            'trimestre.anneeScolaire',
            fn ($q) => $q->whereIn('school_id', Tenant::schoolIds())
        )->findOrFail($request->integer('sequence_id'));

        $result = $this->service->importFromExcel(
            $classeMatiere->classe->school_id, $classeMatiere, $sequence->id, $request->user(), $request->file('file'),
            $request->boolean('confirmer_remplacement'),
        );

        // Au moins une ligne valide remplacerait une note déjà saisie et
        // différente : rien n'a été écrit, l'écran doit faire confirmer
        // explicitement avant de renvoyer la même requête avec le drapeau.
        if ($result['confirmation_requise']) {
            return ApiResponse::error(
                'Cet import remplacerait des notes déjà saisies : ajoutez confirmer_remplacement=1 pour confirmer.',
                409,
                ['conflits' => $result['conflits'], 'failed' => $result['failed'], 'errors' => $result['errors']],
            );
        }

        return ApiResponse::success($result, "{$result['imported']} note(s) importée(s).");
    }

    /** Export des notes d'une affectation/séquence — colonnes alignées avec l'import. */
    public function export(Request $request, int $classeMatiereId): BinaryFileResponse
    {
        $classeMatiere = ClasseMatiere::forSchool(Tenant::schoolIds())->with(['classe', 'matiere'])->findOrFail($classeMatiereId);

        if (! $this->service->peutSaisir($request->user(), $classeMatiere)) {
            abort(403, "Vous n'êtes pas l'enseignant assigné à cette matière pour cette classe.");
        }

        $request->validate(['sequence_id' => ['required', 'integer']]);
        $sequence = Sequence::whereHas(
            'trimestre.anneeScolaire',
            fn ($q) => $q->whereIn('school_id', Tenant::schoolIds())
        )->findOrFail($request->integer('sequence_id'));

        $nomFichier = 'notes-'.Str::slug($classeMatiere->classe->nom.'-'.$classeMatiere->matiere->nom.'-'.$sequence->libelle).'.xlsx';

        return Excel::download(new NoteExport($this->service->lignesExport($classeMatiere, $sequence->id)), $nomFichier);
    }

    /** Modèle vide (matricule + nom déjà remplis, note à saisir) à téléverser via import(). */
    public function modele(Request $request, int $classeMatiereId): BinaryFileResponse
    {
        $classeMatiere = ClasseMatiere::forSchool(Tenant::schoolIds())->with(['classe', 'matiere'])->findOrFail($classeMatiereId);

        if (! $this->service->peutSaisir($request->user(), $classeMatiere)) {
            abort(403, "Vous n'êtes pas l'enseignant assigné à cette matière pour cette classe.");
        }

        $request->validate(['sequence_id' => ['required', 'integer']]);
        $sequence = Sequence::whereHas(
            'trimestre.anneeScolaire',
            fn ($q) => $q->whereIn('school_id', Tenant::schoolIds())
        )->findOrFail($request->integer('sequence_id'));

        $nomFichier = 'modele-notes-'.Str::slug($classeMatiere->classe->nom.'-'.$classeMatiere->matiere->nom).'.xlsx';

        return Excel::download(new NoteExport($this->service->lignesExport($classeMatiere, $sequence->id, pourModele: true)), $nomFichier);
    }
}
