<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDepartementRequest;
use App\Http\Resources\Api\V1\DepartementResource;
use App\Models\Departement;
use App\Models\Trimestre;
use App\Support\Pdf\GenerateurStatistiquesGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DepartementController extends Controller
{
    public function index(): JsonResponse
    {
        $departements = Departement::forSchool(app('tenant.school_id'))
            ->with(['headPersonnel', 'matieres'])
            ->orderBy('nom')
            ->get();

        return ApiResponse::success(DepartementResource::collection($departements));
    }

    public function show(int $id): JsonResponse
    {
        $departement = Departement::forSchool(app('tenant.school_id'))
            ->with(['headPersonnel', 'matieres.classes'])
            ->findOrFail($id);

        return ApiResponse::success(new DepartementResource($departement));
    }

    public function store(StoreDepartementRequest $request): JsonResponse
    {
        $departement = Departement::create([...$request->validated(), 'school_id' => app('tenant.school_id')])
            ->load(['headPersonnel', 'matieres']);

        return ApiResponse::created(new DepartementResource($departement), 'Département créé.');
    }

    public function update(StoreDepartementRequest $request, int $id): JsonResponse
    {
        $departement = Departement::forSchool(app('tenant.school_id'))->findOrFail($id);
        $departement->update($request->validated());
        $departement->load(['headPersonnel', 'matieres']);

        return ApiResponse::success(new DepartementResource($departement), 'Département mis à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $departement = Departement::forSchool(app('tenant.school_id'))->findOrFail($id);
        $departement->delete();

        return ApiResponse::success(message: 'Département supprimé.');
    }

    /**
     * Statistiques pédagogiques par département pour un trimestre donné.
     */
    public function statsPedagogiques(Request $request, int $id): JsonResponse
    {
        $departement = Departement::forSchool(app('tenant.school_id'))->findOrFail($id);

        $trimestre = $this->getTrimestre($request);
        if (!$trimestre) {
            return ApiResponse::error('Aucun trimestre actif trouvé.');
        }

        $matieres = $departement->matieres()->with('classes')->get();

        $donnees = [
            'departement' => [
                'id' => $departement->id,
                'nom' => $departement->nom,
            ],
            'trimestre' => [
                'id' => $trimestre->id,
                'libelle' => $trimestre->libelle,
            ],
            'matieres' => [],
            'stats_consolidees' => [
                'effectif_total' => 0,
                'moyenne_generale' => null,
                'taux_reussite_moyen' => null,
            ],
        ];

        $totalMoyennes = 0;
        $countMatieres = 0;
        $totalTauxReussite = 0;
        $totalEffectif = 0;

        foreach ($matieres as $matiere) {
            $notes = \DB::table('notes')
                ->whereIn('classe_id', $matiere->classes->pluck('id'))
                ->where('trimestre_id', $trimestre->id)
                ->get();

            $effectif = $matiere->classes->sum(fn($c) => $c->eleves_count ?? 0);
            $moyenne = $notes->isNotEmpty() ? $notes->avg('note') : null;
            $tauxReussite = $notes->isNotEmpty()
                ? ($notes->where('note', '>=', 10)->count() / $notes->count() * 100)
                : null;

            $donnees['matieres'][] = [
                'id' => $matiere->id,
                'nom' => $matiere->nom,
                'effectif_eleves' => $effectif,
                'moyenne' => $moyenne ? round($moyenne, 2) : null,
                'taux_reussite' => $tauxReussite ? round($tauxReussite, 2) : null,
            ];

            if ($moyenne) {
                $totalMoyennes += $moyenne;
                $countMatieres++;
            }
            if ($tauxReussite) {
                $totalTauxReussite += $tauxReussite;
            }
            $totalEffectif += $effectif;
        }

        $donnees['stats_consolidees'] = [
            'effectif_total' => $totalEffectif,
            'moyenne_generale' => $countMatieres > 0 ? round($totalMoyennes / $countMatieres, 2) : null,
            'taux_reussite_moyen' => !empty($donnees['matieres'])
                ? round($totalTauxReussite / count($donnees['matieres']), 2)
                : null,
        ];

        return ApiResponse::success($donnees);
    }

    /**
     * Exporte les statistiques pédagogiques en PDF.
     */
    public function exportPdfStatistiques(Request $request, int $id): StreamedResponse
    {
        $departement = Departement::forSchool(app('tenant.school_id'))->findOrFail($id);

        $trimestre = $this->getTrimestre($request);
        if (!$trimestre) {
            abort(404, 'Aucun trimestre actif trouvé.');
        }

        $matieres = $departement->matieres()->with('classes')->get();

        $donnees = [
            'departement' => [
                'id' => $departement->id,
                'nom' => $departement->nom,
            ],
            'trimestre' => [
                'id' => $trimestre->id,
                'libelle' => $trimestre->libelle,
            ],
            'matieres' => [],
            'stats_consolidees' => [
                'effectif_total' => 0,
                'moyenne_generale' => null,
                'taux_reussite_moyen' => null,
            ],
        ];

        $totalMoyennes = 0;
        $countMatieres = 0;
        $totalTauxReussite = 0;
        $totalEffectif = 0;

        foreach ($matieres as $matiere) {
            $notes = \DB::table('notes')
                ->whereIn('classe_id', $matiere->classes->pluck('id'))
                ->where('trimestre_id', $trimestre->id)
                ->get();

            $effectif = $matiere->classes->sum(fn($c) => $c->eleves_count ?? 0);
            $moyenne = $notes->isNotEmpty() ? $notes->avg('note') : null;
            $tauxReussite = $notes->isNotEmpty()
                ? ($notes->where('note', '>=', 10)->count() / $notes->count() * 100)
                : null;

            $donnees['matieres'][] = [
                'id' => $matiere->id,
                'nom' => $matiere->nom,
                'effectif_eleves' => $effectif,
                'moyenne' => $moyenne ? round($moyenne, 2) : null,
                'taux_reussite' => $tauxReussite ? round($tauxReussite, 2) : null,
            ];

            if ($moyenne) {
                $totalMoyennes += $moyenne;
                $countMatieres++;
            }
            if ($tauxReussite) {
                $totalTauxReussite += $tauxReussite;
            }
            $totalEffectif += $effectif;
        }

        $donnees['stats_consolidees'] = [
            'effectif_total' => $totalEffectif,
            'moyenne_generale' => $countMatieres > 0 ? round($totalMoyennes / $countMatieres, 2) : null,
            'taux_reussite_moyen' => !empty($donnees['matieres'])
                ? round($totalTauxReussite / count($donnees['matieres']), 2)
                : null,
        ];

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

    private function getTrimestre(Request $request): ?Trimestre
    {
        $query = Trimestre::whereHas(
            'anneeScolaire',
            fn($q) => $q->where('school_id', app('tenant.school_id'))
        );

        return ($id = $request->integer('trimestre_id'))
            ? $query->find($id)
            : $query->where('is_active', true)->first();
    }
}
