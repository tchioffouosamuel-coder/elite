<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Trimestre;
use App\Services\DisciplineService;
use App\Services\StatistiquesService;
use App\Support\Pdf\StatistiquesGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Statistiques globales de l'établissement, pédagogiques et disciplinaires.
 * Équivalent de `generate_stats_batch_advanced.php` et
 * `generate_disc_stats_batch_advanced.php` dans _smapp — à ceci près que
 * _smapp produisait une archive ZIP d'un PDF par classe, là où l'on rend un
 * document unique où chaque classe occupe sa page.
 */
class StatistiqueController extends Controller
{
    public function __construct(
        private readonly StatistiquesService $service,
        private readonly DisciplineService $discipline,
    ) {}

    public function pedagogiques(Request $request): JsonResponse
    {
        $trimestre = $this->trimestre($request);

        return ApiResponse::success(
            $this->service->pedagogiquesEtablissement(Tenant::schoolId(), $trimestre)
        );
    }

    public function disciplinaires(Request $request): JsonResponse
    {
        $trimestre = $this->trimestre($request);

        return ApiResponse::success(
            $this->service->disciplinairesEtablissement(Tenant::schoolId(), $trimestre, $this->discipline)
        );
    }

    public function pedagogiquesPdf(Request $request): Response
    {
        $trimestre = $this->trimestre($request);
        $donnees = $this->service->pedagogiquesEtablissement(Tenant::schoolId(), $trimestre);

        return $this->pdf(
            (new StatistiquesGenerator)->pedagogiques($donnees, $this->ecole()),
            'stats-pedagogiques-'.Str::slug($trimestre->libelle)
        );
    }

    public function disciplinairesPdf(Request $request): Response
    {
        $trimestre = $this->trimestre($request);
        $donnees = $this->service->disciplinairesEtablissement(Tenant::schoolId(), $trimestre, $this->discipline);

        return $this->pdf(
            (new StatistiquesGenerator)->disciplinaires($donnees, $this->ecole()),
            'stats-disciplinaires-'.Str::slug($trimestre->libelle)
        );
    }

    private function ecole(): School
    {
        return School::findOrFail(Tenant::schoolId());
    }

    private function trimestre(Request $request): Trimestre
    {
        $query = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', Tenant::schoolId())
        );

        return ($id = $request->integer('trimestre_id'))
            ? $query->findOrFail($id)
            : $query->where('is_active', true)->firstOrFail();
    }

    private function pdf(string $contenu, string $nom): Response
    {
        return response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nom.'.pdf"',
        ]);
    }
}
