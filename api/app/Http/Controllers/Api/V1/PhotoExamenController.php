<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Services\PhotoExamenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Photos DECC & OBC — portage de `manage_photos_decc&obc.php` (_smapp).
 * Ne concernent que les classes présentant un examen officiel, repérées par
 * leur `code_examen`.
 */
class PhotoExamenController extends Controller
{
    public function __construct(private readonly PhotoExamenService $service) {}

    /** Classes d'examen de l'établissement. */
    public function classes(): JsonResponse
    {
        $classes = Classe::forSchool(app('tenant.school_id'))
            ->whereNotNull('code_examen')
            ->where('code_examen', '!=', '')
            ->withCount(['eleves as effectif' => fn($q) => $q->where('statut', 'actif')])
            ->withCount(['eleves as photos' => fn($q) => $q->where('statut', 'actif')->whereNotNull('photo_path')])
            ->orderBy('nom')
            ->get()
            // Le sélecteur n'a besoin que de ces quatre champs : renvoyer le
            // modèle entier exposerait des colonnes sans rapport.
            ->map(fn(Classe $c) => [
                'id' => $c->id,
                'nom' => $c->nom,
                'code_examen' => $c->code_examen,
                'effectif' => $c->effectif,
                'photos' => $c->photos,
            ]);

        return ApiResponse::success($classes);
    }

    /** Candidats d'une classe d'examen et état de leurs photos. */
    public function candidats(int $classeId): JsonResponse
    {
        $classe = $this->classeExamen($classeId);

        return ApiResponse::success($this->service->candidats($classe));
    }

    /** Archive ZIP des photos traitées, prête pour le dépôt à l'organisme. */
    public function archive(int $classeId): Response
    {
        $classe = $this->classeExamen($classeId);
        $resultat = $this->service->archive($classe);

        if ($resultat['traites'] === 0) {
            return ApiResponse::error(
                "Aucune photo exploitable dans cette classe. Chargez les photos des candidats avant de générer l'archive.",
                422
            );
        }

        $nom = 'photos-examen-' . Str::slug($classe->nom) . '-' . Str::slug((string) $classe->code_examen) . '.zip';

        return response($resultat['contenu'], 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $nom . '"',
            // Le front lit ces compteurs pour signaler les candidats écartés,
            // qu'un téléchargement binaire ne peut pas rapporter autrement.
            'X-Photos-Traitees' => (string) $resultat['traites'],
            'X-Photos-Ignorees' => (string) count($resultat['ignores']),
            'Access-Control-Expose-Headers' => 'Content-Disposition, X-Photos-Traitees, X-Photos-Ignorees',
        ]);
    }

    private function classeExamen(int $classeId): Classe
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->findOrFail($classeId);

        abort_if(
            blank($classe->code_examen),
            422,
            "Cette classe n'est pas une classe d'examen : renseignez son code d'examen au préalable."
        );

        return $classe;
    }
}
