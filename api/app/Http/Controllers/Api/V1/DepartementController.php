<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\GereImportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDepartementRequest;
use App\Http\Resources\Api\V1\DepartementResource;
use App\Models\Departement;
use App\Models\Trimestre;
use App\Support\ImportExport\SpecificationModele;
use App\Support\ImportExport\Specs\DepartementSpec;
use App\Support\Pdf\GenerateurStatistiquesGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DepartementController extends Controller
{
    use GereImportExport;

    protected function specificationImportExport(): SpecificationModele
    {
        return new DepartementSpec();
    }

    public function index(Request $request): JsonResponse
    {
        $departements = Departement::forSchool(Tenant::schoolIds())
            // Un chef de département n'entre ici que par sa responsabilité :
            // c'est le sien qu'il vient administrer, pas ceux des collègues.
            ->dansPerimetre($request->user())
            ->with(['headPersonnel', 'matieres', 'school:id,name,code,type'])
            ->orderBy('nom')
            ->get();

        return ApiResponse::success(DepartementResource::collection($departements));
    }

    public function show(int $id): JsonResponse
    {
        $departement = Departement::forSchool(Tenant::schoolIds())
            ->with(['headPersonnel', 'matieres.classes', 'school:id,name,code,type'])
            ->findOrFail($id);

        return ApiResponse::success(new DepartementResource($departement));
    }

    public function store(StoreDepartementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        unset($data['school_id']);

        $departement = Departement::create([...$data, 'school_id' => $schoolId])
            ->load(['headPersonnel', 'matieres', 'school:id,name,code,type']);

        return ApiResponse::created(new DepartementResource($departement), 'Département créé.');
    }

    public function update(StoreDepartementRequest $request, int $id): JsonResponse
    {
        $departement = Departement::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $request->validated();
        unset($data['school_id']);
        $departement->update($data);
        $departement->load(['headPersonnel', 'matieres', 'school:id,name,code,type']);

        return ApiResponse::success(new DepartementResource($departement), 'Département mis à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $departement = Departement::forSchool(Tenant::schoolIds())->findOrFail($id);
        $departement->delete();

        return ApiResponse::success(message: 'Département supprimé.');
    }

    /**
     * Statistiques pédagogiques par département pour un trimestre donné.
     */
    public function statsPedagogiques(Request $request, int $id): JsonResponse
    {
        $departement = Departement::forSchool(Tenant::schoolIds())->findOrFail($id);

        $trimestre = $this->getTrimestre($request, $departement->school_id);
        if (!$trimestre) {
            return ApiResponse::error('Aucun trimestre actif trouvé.');
        }

        $donnees = $this->statistiques($departement, $trimestre);

        return ApiResponse::success($donnees);
    }

    /**
     * Exporte les statistiques pédagogiques en PDF.
     */
    public function exportPdfStatistiques(Request $request, int $id): StreamedResponse
    {
        $departement = Departement::forSchool(Tenant::schoolIds())->findOrFail($id);

        $trimestre = $this->getTrimestre($request, $departement->school_id);
        if (!$trimestre) {
            abort(404, 'Aucun trimestre actif trouvé.');
        }

        $donnees = $this->statistiques($departement, $trimestre);

        $generator = new GenerateurStatistiquesGenerator();
        $pdf = $generator->build($donnees);

        $filename = "statistiques_{$departement->id}_{$trimestre->id}_" . date('Ymd_His') . '.pdf';

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf;
            },
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }


    /**
     * Statistiques par matière du département sur le trimestre : moyenne et
     * taux de réussite (note ≥ 10) des notes saisies dans les séquences du
     * trimestre, pour toutes les classes où la matière est enseignée.
     *
     * @return array<string, mixed>
     */
    private function statistiques(Departement $departement, Trimestre $trimestre): array
    {
        $matieres = $departement->matieres()
            ->with(['classes' => fn ($q) => $q->withCount(['eleves' => fn ($e) => $e->where('statut', 'actif')])])
            ->get();
        $sequences = $trimestre->sequences()->pluck('id');

        $lignes = [];
        $effectifTotal = 0;

        foreach ($matieres as $matiere) {
            $notes = DB::table('notes')
                ->join('classe_matieres', 'classe_matieres.id', '=', 'notes.classe_matiere_id')
                ->where('classe_matieres.matiere_id', $matiere->id)
                ->whereIn('notes.sequence_id', $sequences)
                ->whereNotNull('notes.valeur')
                ->pluck('notes.valeur');

            $effectif = $matiere->classes->unique('id')->sum('eleves_count');
            $effectifTotal += $effectif;

            $lignes[] = [
                'id' => $matiere->id,
                'nom' => $matiere->nom,
                'effectif_eleves' => $effectif,
                'moyenne' => $notes->isNotEmpty() ? round($notes->avg(), 2) : null,
                'taux_reussite' => $notes->isNotEmpty() ? round($notes->filter(fn ($n) => $n >= 10)->count() / $notes->count() * 100, 2) : null,
            ];
        }

        $avecNotes = collect($lignes)->whereNotNull('moyenne');

        return [
            'departement' => ['id' => $departement->id, 'nom' => $departement->nom],
            'trimestre' => ['id' => $trimestre->id, 'libelle' => $trimestre->libelle],
            'matieres' => $lignes,
            'stats_consolidees' => [
                'effectif_total' => $effectifTotal,
                'moyenne_generale' => $avecNotes->isNotEmpty() ? round($avecNotes->avg('moyenne'), 2) : null,
                'taux_reussite_moyen' => $avecNotes->isNotEmpty() ? round($avecNotes->avg('taux_reussite'), 2) : null,
            ],
        ];
    }
    private function getTrimestre(Request $request, int $schoolId): ?Trimestre
    {
        $query = Trimestre::whereHas(
            'anneeScolaire',
            fn($q) => $q->where('school_id', $schoolId)
        );

        return ($id = $request->integer('trimestre_id'))
            ? $query->find($id)
            : $query->where('is_active', true)->first();
    }
}
