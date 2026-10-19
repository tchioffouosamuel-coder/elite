<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Services\NotesEnseignantService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NotesEnseignantController extends Controller
{
    public function __construct(private readonly NotesEnseignantService $service) {}

    public function export(Request $request, int $personnelId): BinaryFileResponse
    {
        return $this->telecharger($request, $personnelId, false);
    }

    public function modele(Request $request, int $personnelId): BinaryFileResponse
    {
        return $this->telecharger($request, $personnelId, true);
    }

    private function telecharger(Request $request, int $personnelId, bool $modele): BinaryFileResponse
    {
        [$personnel, $affectations, $periode] = $this->contexte($request, $personnelId);

        return Excel::download(
            $this->service->exporter($affectations, $periode, $modele),
            ($modele ? 'modele-' : '').'notes-'.Str::slug($personnel->nom_complet.'-'.$periode->libelle).'.xlsx',
        );
    }

    public function import(Request $request, int $personnelId): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480']]);
        [$personnel, $affectations, $periode] = $this->contexte($request, $personnelId);
        $resultat = $this->service->importer($affectations, $periode, $request->user(),
            $request->file('file'), $request->boolean('confirmer_remplacement'));
        if ($resultat['confirmation_requise']) {
            return ApiResponse::error('Des notes existent déjà. Confirmez leur remplacement pour importer le classeur.',
                409, ['conflits' => $resultat['conflits'], 'failed' => $resultat['failed'], 'errors' => $resultat['errors']]);
        }

        return ApiResponse::success($resultat, "{$resultat['imported']} note(s) importée(s).");
    }

    private function contexte(Request $request, int $personnelId): array
    {
        $personnel = Personnel::forSchool(Tenant::schoolIds())->with('school')->findOrFail($personnelId);
        abort_unless(in_array($personnel->school?->type, ['secondaire', 'primaire', 'maternelle'], true), 422,
            "L'établissement de cet enseignant n'est pas renseigné.");
        $affectations = $this->service->affectations($personnel, $request->user());
        if ($personnel->school->type === 'secondaire') {
            $request->validate(['sequence_id' => ['required', 'integer']]);
            $periode = Sequence::whereHas('trimestre.anneeScolaire', fn ($q) => $q->where('school_id', $personnel->school_id))
                ->findOrFail($request->integer('sequence_id'));
        } else {
            $request->validate(['trimestre_id' => ['required', 'integer']]);
            $periode = Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', $personnel->school_id))
                ->findOrFail($request->integer('trimestre_id'));
            abort_if($periode->sequencesRetenues()->isEmpty(), 422, 'Aucune séquence configurée pour ce trimestre.');
        }

        return [$personnel, $affectations, $periode];
    }
}
